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
        $closed = Ticket::create($this->ticketData(['reporter_id' => $this->customer->id, 'status' => 'done']));
        Sprint::create(['project_id' => $this->project->id, 'number' => 1, 'status' => 'active']);

        foreach ([$this->admin, $this->dev, $this->customer] as $user) {
            foreach (['fa', 'en'] as $locale) {
                $user->update(['locale' => $locale, 'calendar' => $locale === 'fa' ? 'jalali' : 'gregorian']);
                $this->actingAs($user->fresh());
                $pages = ['/', '/tickets', '/tickets/'.$ticket->number, '/tickets/'.$closed->number, '/tickets?awaiting=me', '/profile', '/projects/'.$this->project->id, '/transactions'];
                if (! $user->isAdmin()) {
                    $pages[] = '/tickets/create';
                }
                if ($user->isStaff()) {
                    $pages = array_merge($pages, ['/projects', '/projects/create', '/projects/'.$this->project->id.'/edit', '/sprints']);
                }
                if ($user->isDeveloper()) {
                    $pages = array_merge($pages, ['/payments/create', '/tickets/'.$ticket->number.'/edit', '/sprints/create',
                        '/customers', '/customers/create', '/customers/'.$this->customer->id.'/edit']);
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
        $this->assertSame(1500000, $ticket->estimated_cost);
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

    public function test_customer_does_not_see_history_of_price_time_and_story_points(): void
    {
        $ticket = Ticket::create($this->ticketData(['reporter_id' => $this->customer->id, 'title' => 'Hist']));
        $service = app(\App\Services\TicketService::class);
        // Only staff-only fields → the whole entry is hidden from the customer.
        $service->update($ticket, ['cost' => 7000000, 'logged_minutes' => 90, 'story_points' => 8], $this->dev);
        // Mixed → the customer sees the status change only.
        $service->update($ticket->fresh(), ['status' => 'testing', 'estimated_cost' => 5000000], $this->dev);

        $fields = fn ($user) => collect($ticket->revisions()->get())
            ->map(fn ($r) => \App\Support\RevisionPresenter::visibleChanges($r->changes, $user))
            ->filter(fn ($c) => $c !== null)->flatMap(fn ($c) => array_keys($c))->sort()->values()->all();

        $this->assertSame(['cost', 'estimated_cost', 'logged_minutes', 'status', 'story_points'], $fields($this->dev));
        $this->assertSame(['status'], $fields($this->customer));

        $page = $this->actingAs($this->customer)->get('/tickets/'.$ticket->number)->assertOk();
        $page->assertSee(TicketStatus::Testing->label());
        $this->assertSame(1, substr_count($page->getContent(), 'change-row'));
        $this->assertSame(2, substr_count($this->actingAs($this->dev)->get('/tickets/'.$ticket->number)->getContent(), '<div class="small"><span class="fw-semibold">'));
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

    public function test_admin_only_reads_tickets_sprints_and_payments(): void
    {
        $ticket = $this->makeTicket();
        $sprint = Sprint::create(['project_id' => $this->project->id, 'number' => 1, 'status' => 'active']);
        $ticket->forceFill(['awaiting_reply' => 'staff'])->save();

        $this->actingAs($this->admin)->get('/tickets/'.$ticket->number)->assertOk()
            ->assertDontSee(route('tickets.edit', $ticket));
        $this->get('/tickets')->assertOk()->assertDontSee(route('tickets.create'));
        $this->get('/sprints')->assertOk()->assertDontSee(route('sprints.create'));

        $this->get('/tickets/create')->assertForbidden();
        $this->post('/tickets', $this->ticketData(['title' => 'By admin']))->assertForbidden();
        $this->get('/tickets/'.$ticket->number.'/edit')->assertForbidden();
        $this->put('/tickets/'.$ticket->number, $this->ticketData())->assertForbidden();
        $this->post('/tickets/'.$ticket->number.'/status', ['status' => 'done'])->assertForbidden();
        $this->post('/tickets/'.$ticket->number.'/followups', ['body' => '<p>Hi</p>'])->assertForbidden();
        $this->post('/tickets/'.$ticket->number.'/read')->assertForbidden();
        $this->delete('/tickets/'.$ticket->number)->assertForbidden();
        $this->get('/sprints/create')->assertForbidden();
        $this->post('/sprints', ['project_id' => $this->project->id, 'status' => 'planned'])->assertForbidden();
        $this->delete('/sprints/'.$sprint->id)->assertForbidden();
        $this->get('/payments/create')->assertForbidden();
        $this->post('/payments', ['customer_id' => $this->customer->id, 'amount' => '100', 'paid_on' => '2026-03-03'])->assertForbidden();

        $this->assertSame(1, Ticket::count());
        $this->assertSame('backlog', $ticket->fresh()->status->value);
        $this->assertSame('staff', $ticket->fresh()->awaiting_reply);
        $this->assertSame(1, Sprint::count());
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

    public function test_current_sprint_folder_uses_the_dates_of_every_sprint_that_includes_today(): void
    {
        $this->travelTo('2026-10-10 12:00');
        $sprint = fn (int $n, ?string $start, ?string $end, string $status = 'planned') => Sprint::create([
            'project_id' => $this->project->id, 'number' => $n, 'start_date' => $start, 'end_date' => $end, 'status' => $status,
        ]);
        $a = $sprint(1, '2026-10-01', '2026-10-10');            // today is the last day
        $b = $sprint(2, '2026-10-10', '2026-10-20');            // overlaps: today is the first day
        $past = $sprint(3, '2026-09-01', '2026-09-30', 'active'); // status does not matter, only dates
        $noDates = $sprint(4, null, null, 'active');

        foreach ([[$a, 'done'], [$b, 'backlog'], [$past, 'testing'], [$noDates, 'testing'], [null, 'testing']] as $i => [$s, $status]) {
            Ticket::create($this->ticketData(['reporter_id' => $this->dev->id, 'title' => "T{$i}", 'status' => $status, 'sprint_id' => $s?->id]));
        }

        $titles = \App\Support\TicketFilter::query(['sprint_id' => 'current'], $this->dev, app(\App\Support\ProjectContext::class))
            ->pluck('title')->sort()->values()->all();
        $this->assertSame(['T0', 'T1'], $titles);

        $item = collect((new \App\Support\TicketMenus($this->dev, app(\App\Support\ProjectContext::class)))->items())->firstWhere('key', 'current_sprint');
        $this->assertSame(2, $item['count']);
        $this->actingAs($this->dev)->get($item['url'])->assertOk()->assertSee('T0')->assertDontSee('T2');

        // Green everywhere: only sprints 1 and 2 are current.
        $this->assertTrue($a->isCurrent());
        $this->assertTrue($b->isCurrent());
        $this->assertFalse($past->isCurrent());
        $this->assertFalse($noDates->isCurrent());
        $page = $this->actingAs($this->dev)->get('/sprints')->assertOk()->getContent();
        $this->assertSame(2, substr_count($page, 'row-current-sprint'));
        $this->actingAs($this->dev)->get('/tickets')->assertOk()->assertSee('sprint-current');
    }

    public function test_builtin_folders_can_be_reordered_hidden_and_reset(): void
    {
        $keys = fn (User $u) => collect((new \App\Support\TicketMenus($u, app(\App\Support\ProjectContext::class)))->items())
            ->reject(fn ($i) => $i['hidden'])->pluck('key')->all();
        // Default (the owner's choice): "all" first; "open", cancelled, rejected, mine, unassigned, reported hidden.
        $default = $keys($this->customer);
        $this->assertSame('all', $default[0]);
        $this->assertNotContains('open', $default);
        $this->assertNotContains('reported', $default);

        // Customer: "all" first, "open" hidden, an unknown key is dropped; missing folders come at the end.
        $this->actingAs($this->customer)->put('/folder-settings', [
            'order' => ['all', 'open', 'reported', 'bogus'],
            'visible' => ['all', 'reported'],
        ])->assertRedirect();
        $this->assertSame(['order' => ['all', 'open', 'reported'], 'hidden' => ['open']], $this->customer->fresh()->folder_settings);

        $now = $keys($this->customer->fresh());
        $this->assertSame(['all', 'reported'], array_slice($now, 0, 2));
        $this->assertNotContains('open', $now);
        $this->assertContains('awaiting', $now);

        // A hidden folder still opens by URL.
        $this->actingAs($this->customer->fresh())->get('/tickets?status[]=backlog')->assertOk();

        $this->actingAs($this->customer)->delete('/folder-settings')->assertRedirect();
        $this->assertNull($this->customer->fresh()->folder_settings);
        $this->assertSame($default, $keys($this->customer->fresh()));
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
        $this->actingAs($this->dev)->get('/tickets?q=x')->assertOk()->assertViewHas('grid', fn ($g) => $g->visible('cost') && ! $g->visible('status'));
    }

    public function test_folders_have_default_columns(): void
    {
        // Nobody changed anything: each folder shows its default columns.
        $this->actingAs($this->customer)->get('/tickets?status[]=done')->assertOk()
            ->assertViewHas('grid', fn ($g) => $g->visible('sprint') && $g->visible('cost') && ! $g->visible('type') && ! $g->visible('status'));
        $this->actingAs($this->customer)->get('/tickets?status[]=pending_review')->assertOk()
            ->assertViewHas('grid', fn ($g) => $g->visible('priority') && ! $g->visible('sprint') && ! $g->visible('status'));
        $this->actingAs($this->customer)->get('/tickets?q=x')->assertOk()
            ->assertViewHas('grid', fn ($g) => $g->visible('status') && $g->visible('cost')); // search page
    }

    public function test_each_ticket_folder_keeps_its_own_columns(): void
    {
        // The search page choice is the fallback of every folder.
        $this->actingAs($this->dev)->postJson('/grid-preferences', ['grid' => 'tickets', 'columns' => ['number', 'title', 'status']])->assertOk();
        $this->actingAs($this->dev)->postJson('/grid-preferences', ['grid' => 'tickets-status_done', 'columns' => ['number', 'title', 'cost']])->assertOk();

        $this->actingAs($this->dev)->get('/tickets?status[]=done')->assertOk()
            ->assertViewHas('grid', fn ($g) => $g->key === 'tickets-status_done' && $g->visible('cost') && ! $g->visible('status'));
        // A folder without default columns (testing) falls back to the search page choice;
        // a folder with default columns (backlog) keeps its defaults.
        $this->actingAs($this->dev)->get('/tickets?status[]=testing')->assertOk()
            ->assertViewHas('grid', fn ($g) => $g->key === 'tickets-status_testing' && $g->visible('status') && ! $g->visible('cost'));
        $this->actingAs($this->dev)->get('/tickets?status[]=backlog')->assertOk()
            ->assertViewHas('grid', fn ($g) => $g->key === 'tickets-status_backlog' && $g->visible('estimated_cost') && ! $g->visible('status'));
        $this->actingAs($this->dev)->get('/tickets?q=xyz')->assertOk()
            ->assertViewHas('grid', fn ($g) => $g->key === 'tickets' && $g->visible('status'));
    }

    public function test_developer_bulk_actions_on_tickets(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'number' => 1, 'start_date' => '2026-10-01', 'end_date' => '2026-10-14']);
        $this->otherProject->members()->attach($this->dev->id);
        $a = Ticket::create($this->ticketData(['reporter_id' => $this->dev->id]));
        $b = Ticket::create($this->ticketData(['reporter_id' => $this->dev->id]));
        $other = Ticket::create($this->ticketData(['project_id' => $this->otherProject->id, 'reporter_id' => $this->dev->id]));
        $ids = [$a->id, $b->id, $other->id];

        $this->actingAs($this->dev)->get('/tickets')->assertSee('bulk-form', false);
        $this->actingAs($this->customer)->get('/tickets')->assertDontSee('bulk-form', false);

        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => $ids, 'action' => 'status', 'status' => 'testing'])
            ->assertSessionHasNoErrors()->assertSessionMissing('warning');
        $this->assertSame(3, Ticket::where('status', 'testing')->count());
        $this->assertSame('status_changed', $a->revisions()->latest('id')->first()->action);

        // The sprint is in project one, so the ticket of project two is skipped.
        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => $ids, 'action' => 'sprint', 'sprint_id' => $sprint->id])
            ->assertSessionHas('warning');
        $this->assertSame($sprint->id, $a->fresh()->sprint_id);
        $this->assertNull($other->fresh()->sprint_id);

        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$a->id], 'action' => 'sprint', 'sprint_id' => 'none']);
        $this->assertNull($a->fresh()->sprint_id);

        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$a->id, $b->id], 'action' => 'assignee', 'assignee_id' => $this->dev->id]);
        $this->assertSame($this->dev->id, $b->fresh()->assignee_id);
        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$a->id], 'action' => 'assignee', 'assignee_id' => $this->otherDev->id])
            ->assertSessionHas('warning');
        $this->assertSame($this->dev->id, $a->fresh()->assignee_id);

        // One fixed cost for many small tickets (with history); no decimals; 0 removes it.
        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$a->id, $b->id], 'action' => 'cost', 'cost' => '1.5'])
            ->assertSessionHasErrors('cost');
        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$a->id, $b->id], 'action' => 'cost', 'cost' => '۲۵۰,۰۰۰'])
            ->assertSessionHasNoErrors();
        $this->assertSame([250000, 250000], [$a->fresh()->cost, $b->fresh()->cost]);
        $this->assertSame(['old' => null, 'new' => 250000], array_map(fn ($v) => $v === null ? null : (int) $v, $a->revisions()->latest('id')->first()->changes['cost']));
        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$b->id], 'action' => 'cost', 'cost' => '0']);
        $this->assertNull($b->fresh()->cost);

        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$a->id], 'action' => 'priority', 'priority' => 'lowest']);
        $this->assertSame('lowest', $a->fresh()->priority->value);

        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$b->id], 'action' => 'delete']);
        $this->assertSoftDeleted($b);

        // A missing parameter is a validation error; other developers' tickets are skipped; customers and admins are refused.
        $this->actingAs($this->dev)->post('/tickets/bulk', ['ids' => [$a->id], 'action' => 'status'])->assertSessionHasErrors('status');
        $foreign = Ticket::create($this->ticketData(['project_id' => $this->otherProject->id, 'reporter_id' => $this->otherDev->id]));
        $this->otherProject->members()->detach($this->dev->id);
        $this->actingAs($this->dev->fresh())->post('/tickets/bulk', ['ids' => [$foreign->id], 'action' => 'priority', 'priority' => 'lowest'])->assertSessionHas('warning');
        $this->assertSame('high', $foreign->fresh()->priority->value);
        $this->actingAs($this->customer)->post('/tickets/bulk', ['ids' => [$a->id], 'action' => 'delete'])->assertForbidden();
        $this->actingAs($this->admin)->post('/tickets/bulk', ['ids' => [$a->id], 'action' => 'delete'])->assertForbidden();
    }

    public function test_overlapping_sprint_dates_are_only_a_warning(): void
    {
        $one = Sprint::create(['project_id' => $this->project->id, 'number' => 1, 'start_date' => '2026-10-05', 'end_date' => '2026-10-12', 'status' => 'active']);
        // Starts the day after: no conflict. A gap is fine too.
        $two = Sprint::create(['project_id' => $this->project->id, 'number' => 2, 'start_date' => '2026-10-13', 'end_date' => '2026-10-20', 'status' => 'planned']);
        Sprint::create(['project_id' => $this->project->id, 'number' => 3, 'start_date' => '2026-11-01', 'end_date' => '2026-11-10', 'status' => 'planned']);
        // Other projects never conflict.
        Sprint::create(['project_id' => $this->otherProject->id, 'number' => 1, 'start_date' => '2026-10-10', 'end_date' => '2026-10-15', 'status' => 'planned']);
        $this->assertSame([], Sprint::dateConflicts([$this->project->id, $this->otherProject->id]));
        $this->assertSame(8, $one->days()); // 5th → 12th, both days included
        $this->assertNull((new Sprint(['start_date' => '2026-10-05']))->days());

        // Saving sprint 2 to start on the last day of sprint 1 works, with a warning.
        $this->actingAs($this->dev)->put('/sprints/'.$two->id, ['project_id' => $this->project->id, 'number' => 2,
            'start_date' => '2026-10-12', 'end_date' => '2026-10-20', 'status' => 'planned'])
            ->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->assertSame('2026-10-12', $two->fresh()->start_date->toDateString());

        $conflicts = Sprint::dateConflicts([$this->project->id]);
        $this->assertSame(['end'], array_keys($conflicts[$one->id]));
        $this->assertSame(['start'], array_keys($conflicts[$two->id]));
        $this->assertCount(2, $conflicts);

        $this->actingAs($this->dev)->get('/sprints')->assertOk()->assertViewHas('conflicts', $conflicts)->assertSee('text-danger', false);
    }

    public function test_new_sprint_starts_the_day_after_the_last_sprint_of_the_project(): void
    {
        $this->actingAs($this->dev)->get('/sprints/create')->assertOk()
            ->assertViewHas('sprint', fn ($s) => $s->number === 1 && $s->start_date === null);

        Sprint::create(['project_id' => $this->project->id, 'number' => 1, 'start_date' => '2026-10-05', 'end_date' => '2026-10-16', 'status' => 'closed']);
        Sprint::create(['project_id' => $this->project->id, 'number' => 2, 'start_date' => '2026-10-17', 'end_date' => '2026-10-23', 'status' => 'active']);
        Sprint::create(['project_id' => $this->project->id, 'number' => 3, 'status' => 'planned']); // no dates: ignored

        // 23rd is a Friday: the next sprint still starts on Saturday the 24th (weekends included).
        $this->actingAs($this->dev)->get('/sprints/create')->assertOk()
            ->assertViewHas('sprint', fn ($s) => $s->number === 4 && $s->start_date->toDateString() === '2026-10-24')
            ->assertViewHas('defaults', fn ($d) => $d[$this->project->id]['number'] === 4 && $d[$this->project->id]['start_date'] !== '')
            ->assertSee('data-sprint-defaults', false);
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

    public function test_followups_switch_the_waiting_side_and_show_red_badges(): void
    {
        $ticket = Ticket::create($this->ticketData(['reporter_id' => $this->customer->id, 'status' => 'pending_review']));
        $url = '/tickets/'.$ticket->number;

        // Customer asks a question: the ticket now waits for staff.
        $this->actingAs($this->customer)->post($url.'/followups', [
            'body' => '<p>Any news?</p>',
            'attachments' => [UploadedFile::fake()->create('log.txt', 3)],
        ])->assertRedirect();
        $ticket->refresh();
        $this->assertSame('staff', $ticket->awaiting_reply);
        $followup = $ticket->followups()->first();
        $this->assertCount(1, $followup->attachments);
        $this->assertCount(0, $ticket->attachments, 'followup files are not ticket files');

        // Developer sees the red badge (sidebar) and the "Waiting for my reply" folder.
        $this->actingAs($this->dev)->get('/tickets?awaiting=me')->assertOk()->assertSee('side-badge-alert', false)->assertSee($ticket->title);
        $this->actingAs($this->customer)->get('/tickets')->assertDontSee('side-badge-alert', false);

        // Developer replies and keeps the default: now the customer must answer.
        $this->actingAs($this->dev)->post($url.'/followups', ['body' => '<p>Please test it.</p>', 'awaits_reply' => '1'])->assertRedirect();
        $this->assertSame('customer', $ticket->fresh()->awaiting_reply);

        // Login toast for the customer.
        auth()->logout();
        $this->post('/login', ['login' => $this->customer->email, 'password' => 'password'])->assertSessionHas('awaiting_toast', 1);

        // "I read it" clears it, only for the waiting side.
        $this->actingAs($this->dev)->post($url.'/read')->assertForbidden();
        $this->actingAs($this->customer)->post($url.'/read')->assertRedirect();
        $this->assertNull($ticket->fresh()->awaiting_reply);

        // A staff reply with the box unticked does not wait for anyone.
        $this->actingAs($this->customer)->post($url.'/followups', ['body' => '<p>Works, thanks.</p>']);
        $this->actingAs($this->dev)->post($url.'/followups', ['body' => '<p>Great.</p>', 'awaits_reply' => '0']);
        $this->assertNull($ticket->fresh()->awaiting_reply);
        $this->assertSame(4, $ticket->followups()->count());

        // Empty reply is refused.
        $this->actingAs($this->dev)->post($url.'/followups', ['body' => ''])->assertSessionHasErrors('body');
    }

    public function test_closed_tickets_cannot_be_replied_by_customers_and_get_one_rating(): void
    {
        $ticket = Ticket::create($this->ticketData(['reporter_id' => $this->dev->id, 'status' => 'in_progress']));
        $url = '/tickets/'.$ticket->number;

        // No rating while the ticket is open.
        $this->actingAs($this->customer)->post($url.'/comments', ['rating' => 5])->assertForbidden();

        $ticket->update(['status' => 'done']);
        $this->actingAs($this->customer)->post($url.'/followups', ['body' => '<p>Hi</p>'])->assertForbidden();
        $this->actingAs($this->dev)->post($url.'/followups', ['body' => '<p>Deployed.</p>', 'awaits_reply' => '0'])->assertRedirect();

        // Developers cannot rate, customers can (stars only, text optional).
        $this->actingAs($this->dev)->post($url.'/comments', ['rating' => 5])->assertForbidden();
        $this->actingAs($this->customer)->post($url.'/comments', ['rating' => 6])->assertSessionHasErrors('rating');
        $this->actingAs($this->customer)->post($url.'/comments', ['rating' => 4])->assertRedirect();
        $this->actingAs($this->customer)->post($url.'/comments', ['rating' => 3, 'body' => 'Good'])->assertRedirect();
        $this->assertSame(1, $ticket->comment()->count());
        $this->assertSame(3, $ticket->comment->rating);

        $this->actingAs($this->dev)->get($url)->assertOk()->assertSee('Good');
    }

    public function test_money_is_always_a_whole_number(): void
    {
        $this->actingAs($this->dev)->post('/tickets', $this->ticketData(['cost' => '7,000,000', 'estimated_cost' => '12.5']))
            ->assertSessionHasErrors('estimated_cost');
        $this->actingAs($this->dev)->post('/tickets', $this->ticketData(['cost' => '7,000,000']))->assertRedirect();
        $ticket = Ticket::first();
        $this->assertSame(7000000, $ticket->cost);

        foreach (['/tickets/'.$ticket->number, '/tickets/'.$ticket->number.'/edit'] as $page) {
            $this->get($page)->assertOk()->assertDontSee('7000000.00')->assertDontSee('7,000,000.00');
        }
        $this->get('/tickets/'.$ticket->number)->assertSee('7,000,000');
    }

    public function test_new_ticket_form_assigns_the_developer_by_default(): void
    {
        $selected = fn (User $u) => 'value="'.$u->id.'" data-projects="'.$this->project->id.'" selected';
        $this->actingAs($this->dev)->get('/tickets/create')->assertOk()->assertSee($selected($this->dev), false);

        $ticket = $this->makeTicket();
        $this->actingAs($this->dev)->get('/tickets/'.$ticket->number.'/edit')->assertOk()->assertDontSee('data-default', false);
    }

    public function test_saving_a_form_goes_back_to_the_list_page(): void
    {
        $ticket = $this->makeTicket();
        $folder = url('/tickets?status%5B0%5D=backlog&page=2');

        // Back to the folder the user came from.
        $this->actingAs($this->dev)->put('/tickets/'.$ticket->number, $this->ticketData(['_back' => $folder]))->assertRedirect($folder);
        // Opened directly (no list page): all tickets.
        $this->actingAs($this->dev)->put('/tickets/'.$ticket->number, $this->ticketData())->assertRedirect('/tickets');
        // Other sites are never used.
        $this->actingAs($this->dev)->put('/tickets/'.$ticket->number, $this->ticketData(['_back' => 'https://evil.example/x']))->assertRedirect('/tickets');
        $this->actingAs($this->dev)->put('/tickets/'.$ticket->number, $this->ticketData(['_back' => url('/').'.evil.example/x']))->assertRedirect('/tickets');

        // Same for other forms.
        $users = url('/admin/users?role=customer');
        $this->actingAs($this->admin)->put('/admin/users/'.$this->customer->id, [
            'first_name' => 'New', 'last_name' => 'Name', 'mobile' => $this->customer->mobile, 'email' => $this->customer->email,
            'role' => 'customer', 'locale' => 'fa', 'calendar' => 'jalali', 'is_active' => '1', '_back' => $users,
        ])->assertSessionHasNoErrors()->assertRedirect($users);
    }

    public function test_list_referrer_is_only_an_app_list_page(): void
    {
        $ticket = $this->makeTicket();
        $page = fn (string $referer) => $this->actingAs($this->dev)->withHeader('referer', $referer)->get('/tickets/'.$ticket->number);

        $page(url('/tickets?status=backlog'))->assertSee('data-list-referrer="'.e(url('/tickets?status=backlog')).'"', false);
        $page(url('/tickets/'.$ticket->number.'/edit'))->assertSee('data-list-referrer=""', false);
        $page(url('/login'))->assertSee('data-list-referrer=""', false);
        $page('https://evil.example/tickets')->assertSee('data-list-referrer=""', false);
    }

    private function makeTicket(): Ticket
    {
        $this->actingAs($this->dev)->post('/tickets', $this->ticketData())->assertSessionHasNoErrors();

        return Ticket::latest('id')->first();
    }
}
