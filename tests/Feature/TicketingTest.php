<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dev;

    private User $otherDev;

    private User $customer;

    private Project $project;

    private Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->admin()->create();
        $this->dev = User::factory()->developer()->create();
        $this->otherDev = User::factory()->developer()->create();
        $this->customer = User::factory()->customer()->create();

        $this->project = Project::create(['code' => 'P1', 'name' => 'Project one']);
        $this->otherProject = Project::create(['code' => 'P2', 'name' => 'Project two']);
        $this->project->members()->attach([$this->dev->id, $this->customer->id]);
        $this->otherProject->members()->attach([$this->otherDev->id]);
    }

    private function ticketData(array $extra = []): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'type' => 'task',
            'priority' => 'high',
            'status' => 'backlog',
            'title' => 'Login page is broken',
            'content' => '<p>Hello <script>alert(1)</script><b>world</b></p>',
        ], $extra);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_login_with_mobile(): void
    {
        $this->post('/login', ['login' => $this->dev->mobile, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->dev);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->dev->update(['is_active' => false]);
        $this->post('/login', ['login' => $this->dev->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_all_pages_render_for_every_role(): void
    {
        $ticket = Ticket::create($this->ticketData(['reporter_id' => $this->dev->id]));
        Sprint::create(['project_id' => $this->project->id, 'number' => 1, 'status' => 'active']);

        foreach ([$this->admin, $this->dev, $this->customer] as $user) {
            foreach (['fa', 'en'] as $locale) {
                $user->update(['locale' => $locale, 'calendar' => $locale === 'fa' ? 'jalali' : 'gregorian']);
                $this->actingAs($user->fresh());
                $pages = ['/', '/tickets', '/tickets/create', '/tickets/'.$ticket->number, '/profile', '/projects/'.$this->project->id];
                if ($user->isStaff()) {
                    $pages = array_merge($pages, ['/tickets/'.$ticket->number.'/edit', '/projects', '/projects/create',
                        '/projects/'.$this->project->id.'/edit', '/sprints', '/sprints/create']);
                }
                if ($user->isDeveloper()) {
                    $pages = array_merge($pages, ['/customers', '/customers/create', '/customers/'.$this->customer->id.'/edit']);
                }
                if ($user->isAdmin()) {
                    $pages = array_merge($pages, ['/admin/users', '/admin/users/create', '/admin/users/'.$this->dev->id.'/edit']);
                }
                foreach ($pages as $page) {
                    $this->get($page)->assertOk();
                }
            }
        }
    }

    public function test_role_restricted_pages(): void
    {
        $this->actingAs($this->customer)->get('/projects')->assertForbidden();
        $this->actingAs($this->customer)->get('/sprints')->assertForbidden();
        $this->actingAs($this->customer)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->dev)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->admin)->get('/customers')->assertForbidden();
    }

    public function test_ticket_number_is_id_plus_two_random_digits(): void
    {
        $this->actingAs($this->dev)->post('/tickets', $this->ticketData())->assertRedirect();
        $ticket = Ticket::first();
        $this->assertSame($ticket->id, intdiv($ticket->number, 100));
        $this->assertGreaterThanOrEqual(10, $ticket->number % 100);
        // HTML is sanitised.
        $this->assertStringNotContainsString('<script', $ticket->content);
        $this->assertStringContainsString('<b>world</b>', $ticket->content);
    }

    public function test_customer_ticket_starts_pending_and_locks_after_acceptance(): void
    {
        $this->actingAs($this->customer)->post('/tickets', $this->ticketData(['status' => 'done', 'cost' => '999']))->assertRedirect();
        $ticket = Ticket::first();
        $this->assertSame(TicketStatus::PendingReview, $ticket->status);
        $this->assertNull($ticket->cost, 'customers cannot set staff fields');

        // Still pending: the customer can edit.
        $this->actingAs($this->customer)->put('/tickets/'.$ticket->number, $this->ticketData(['title' => 'Edited']))->assertRedirect();
        $this->assertSame('Edited', $ticket->fresh()->title);

        // Developer accepts the ticket.
        $this->actingAs($this->dev)->post('/tickets/'.$ticket->number.'/status', ['status' => 'backlog'])->assertRedirect();

        $this->actingAs($this->customer)->put('/tickets/'.$ticket->number, $this->ticketData(['title' => 'Again']))->assertForbidden();
        $this->actingAs($this->customer)->delete('/tickets/'.$ticket->number)->assertForbidden();
        $this->actingAs($this->customer)->post('/tickets/'.$ticket->number.'/status', ['status' => 'done'])->assertForbidden();

        // ...but can still cancel it.
        $this->actingAs($this->customer)->post('/tickets/'.$ticket->number.'/status', ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(TicketStatus::Cancelled, $ticket->fresh()->status);
    }

    public function test_developer_edits_are_kept_in_history_and_delete_is_soft(): void
    {
        $this->actingAs($this->dev)->post('/tickets', $this->ticketData([
            'estimated_time' => '02:30', 'estimated_cost' => '1,500,000', 'story_points' => 5, 'due_date' => '1405/07/10',
            'assignee_id' => $this->dev->id,
        ]))->assertSessionHasNoErrors();
        $ticket = Ticket::first();
        $this->assertSame(150, $ticket->estimated_minutes);
        $this->assertSame('1500000.00', $ticket->estimated_cost);
        $this->assertSame('2026-10-02', $ticket->due_date->toDateString());

        $this->actingAs($this->dev)->put('/tickets/'.$ticket->number, $this->ticketData(['title' => 'New title', 'status' => 'testing']))
            ->assertSessionHasNoErrors();
        $revision = $ticket->revisions()->first();
        $this->assertSame('updated', $revision->action);
        $this->assertSame(['old' => 'Login page is broken', 'new' => 'New title'], $revision->changes['title']);
        $this->assertSame('testing', $revision->changes['status']['new']);

        $this->actingAs($this->dev)->delete('/tickets/'.$ticket->number)->assertRedirect('/tickets');
        $this->assertSoftDeleted($ticket);
        $this->assertSame($this->dev->id, Ticket::withTrashed()->find($ticket->id)->deleted_by);
        $this->assertSame(3, $ticket->revisions()->count());
    }

    public function test_developer_only_sees_own_projects(): void
    {
        $foreign = Ticket::create($this->ticketData(['project_id' => $this->otherProject->id, 'reporter_id' => $this->otherDev->id, 'title' => 'Secret']));

        $this->actingAs($this->dev)->get('/tickets/'.$foreign->number)->assertForbidden();
        $this->actingAs($this->dev)->get('/tickets')->assertDontSee('Secret');
        $this->actingAs($this->dev)->get('/projects/'.$this->otherProject->id.'/edit')->assertForbidden();
        $this->actingAs($this->dev)->post('/tickets', $this->ticketData(['project_id' => $this->otherProject->id]))->assertSessionHasErrors('project_id');
        $this->actingAs($this->admin)->get('/tickets')->assertSee('Secret');
    }

    public function test_developer_manages_only_own_customers(): void
    {
        $stranger = User::factory()->customer()->create();
        $this->otherProject->members()->attach($stranger->id);

        $this->actingAs($this->dev)->get('/customers/'.$stranger->id.'/edit')->assertForbidden();

        $this->actingAs($this->dev)->post('/customers', [
            'first_name' => 'New', 'last_name' => 'Customer', 'email' => 'new@example.com', 'mobile' => '09350000000',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'locale' => 'fa', 'calendar' => 'jalali',
            'is_active' => 1, 'projects' => [$this->project->id, $this->otherProject->id],
        ])->assertRedirect('/customers');
        $new = User::where('email', 'new@example.com')->first();
        $this->assertSame([$this->project->id], $new->projects()->pluck('projects.id')->all(), 'cannot assign to foreign project');
        $this->assertSame($this->dev->id, $new->created_by);

        // Removing a shared customer keeps their other developer's project.
        $this->otherProject->members()->attach($this->customer->id);
        $this->actingAs($this->dev)->delete('/customers/'.$this->customer->id)->assertRedirect();
        $this->assertSame([$this->otherProject->id], $this->customer->projects()->pluck('projects.id')->all());
        $this->assertNotSoftDeleted($this->customer);
    }

    public function test_admin_assigns_projects_but_not_to_admins(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'role' => 'admin', 'first_name' => 'A', 'last_name' => 'B', 'email' => 'a2@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'locale' => 'en', 'calendar' => 'gregorian',
            'is_active' => 1, 'projects' => [$this->project->id],
        ])->assertRedirect();
        $this->assertSame(0, User::where('email', 'a2@example.com')->first()->projects()->count());
    }

    public function test_customer_cannot_choose_all_projects(): void
    {
        $this->actingAs($this->customer)->post('/switch-project', ['project_id' => 'all'])->assertForbidden();
        $this->actingAs($this->dev)->post('/switch-project', ['project_id' => 'all'])->assertRedirect();
        $this->actingAs($this->dev)->post('/switch-project', ['project_id' => $this->otherProject->id])->assertForbidden();
    }

    public function test_custom_menu_is_created_and_highlighted(): void
    {
        $response = $this->actingAs($this->dev)->postJson('/ticket-menus', [
            'name' => 'Urgent bugs',
            'filters' => ['priority' => ['highest', 'high'], 'type' => ['bug'], 'per_page' => 50],
        ])->assertOk();
        $url = $response->json('url');
        $this->assertStringContainsString('priority', $url);

        $page = $this->actingAs($this->dev)->get($url)->assertOk();
        $page->assertSee('Urgent bugs');

        $menu = $this->dev->ticketMenus()->first();
        $this->assertSame(['priority' => ['high', 'highest'], 'type' => ['bug']], $menu->filters);

        $this->actingAs($this->customer)->put('/ticket-menus/'.$menu->id, ['name' => 'x', 'sort_order' => 1])->assertForbidden();
        $this->actingAs($this->dev)->put('/ticket-menus/'.$menu->id, ['name' => 'Renamed', 'sort_order' => 3])->assertRedirect();
        $this->assertSame('Renamed', $menu->fresh()->name);
        $this->actingAs($this->dev)->delete('/ticket-menus/'.$menu->id)->assertRedirect();
        $this->assertModelMissing($menu);
    }

    public function test_filters_and_project_override(): void
    {
        Ticket::create($this->ticketData(['reporter_id' => $this->dev->id, 'title' => 'Alpha', 'status' => 'testing']));
        Ticket::create($this->ticketData(['reporter_id' => $this->dev->id, 'title' => 'Beta', 'status' => 'done']));
        $this->otherProject->members()->attach($this->dev->id);
        Ticket::create($this->ticketData(['project_id' => $this->otherProject->id, 'reporter_id' => $this->dev->id, 'title' => 'Gamma']));

        $this->actingAs($this->dev)->post('/switch-project', ['project_id' => $this->project->id]);
        $this->actingAs($this->dev->fresh())->get('/tickets?status[]=testing')->assertSee('Alpha')->assertDontSee('Beta')->assertDontSee('Gamma');
        // An explicit project in the filter wins over the top-bar project.
        $this->actingAs($this->dev->fresh())->get('/tickets?project_id='.$this->otherProject->id)->assertSee('Gamma')->assertDontSee('Alpha');
    }

    public function test_grid_preferences_are_saved_on_server(): void
    {
        $this->actingAs($this->dev)->postJson('/grid-preferences', ['grid' => 'tickets', 'columns' => ['number', 'title', 'cost']])->assertOk();
        $this->assertDatabaseHas('grid_preferences', ['user_id' => $this->dev->id, 'grid_key' => 'tickets']);
        $this->actingAs($this->dev)->get('/tickets')->assertOk()->assertViewHas('grid', fn ($g) => $g->visible('cost') && ! $g->visible('status'));
    }

    public function test_attachments_are_private_and_editor_upload_works(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs($this->dev)->post('/tickets', $this->ticketData([
            'attachments' => [UploadedFile::fake()->create('spec.pdf', 200, 'application/pdf')],
        ]))->assertSessionHasNoErrors();
        $attachment = Ticket::first()->attachments()->first();
        $this->assertSame('spec.pdf', $attachment->original_name);

        $this->actingAs($this->dev)->get('/attachments/'.$attachment->id)->assertOk();
        $this->actingAs($this->otherDev)->get('/attachments/'.$attachment->id)->assertForbidden();

        $this->actingAs($this->dev)->post('/tickets', $this->ticketData([
            'attachments' => [UploadedFile::fake()->create('evil.php', 1)],
        ]))->assertSessionHasErrors('attachments.0');

        $this->actingAs($this->dev)->post('/editor/upload', ['file' => UploadedFile::fake()->image('a.png')])
            ->assertOk()->assertJsonStructure(['location']);
    }

    public function test_profile_base_info_is_read_only_for_non_admins(): void
    {
        $this->actingAs($this->customer)->put('/profile', [
            'first_name' => 'Hacked', 'email' => 'x@example.com', 'locale' => 'en', 'calendar' => 'gregorian',
        ])->assertRedirect();
        $fresh = $this->customer->fresh();
        $this->assertNotSame('Hacked', $fresh->first_name);
        $this->assertSame('en', $fresh->locale);
        $this->assertSame('gregorian', $fresh->calendar);

        $this->actingAs($fresh)->get('/')->assertSee('dir="ltr"', false);
    }
}
