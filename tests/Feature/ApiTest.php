<?php

namespace Tests\Feature;

use App\Models\ApiAuthRequest;
use App\Models\ApiToken;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private User $customer;

    private Project $project;

    private Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->dev = User::factory()->developer()->create(['first_name' => 'Sara', 'last_name' => 'Dev']);
        $this->customer = User::factory()->customer()->create();
        $this->project = Project::create(['code' => 'P1', 'name' => 'Project one']);
        $this->otherProject = Project::create(['code' => 'P2', 'name' => 'Project two']);
        $this->project->members()->attach([$this->dev->id, $this->customer->id]);
        $this->otherProject->members()->attach([$this->dev->id]);
    }

    /** Run the 3 login steps and return the plain token. */
    private function login(?string $project = null): string
    {
        $start = $this->postJson('/api/auth/start', ['name' => 'Telegram bot'])->assertOk()->json();
        $path = parse_url($start['login_url'], PHP_URL_PATH);

        $this->actingAs($this->dev)->get($path)->assertOk()->assertSee('Project one');
        $this->actingAs($this->dev)->post($path, ['project' => $project ?? (string) $this->project->id])->assertOk();

        return $this->postJson('/api/auth/token', ['request_id' => $start['request_id'], 'secret' => $start['secret']])
            ->assertOk()->assertJsonPath('status', 'approved')->json('token');
    }

    private function api(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_full_login_flow_links_the_project(): void
    {
        $start = $this->postJson('/api/auth/start')->assertOk()->json();
        $this->assertStringContainsString('/bot-login/', $start['login_url']);

        // Before the developer signs in: pending.
        $this->postJson('/api/auth/token', ['request_id' => $start['request_id'], 'secret' => $start['secret']])
            ->assertStatus(202)->assertJsonPath('status', 'pending');

        // Guest opens the link → sent to the login page, then back to the link.
        $path = parse_url($start['login_url'], PHP_URL_PATH);
        $this->get($path)->assertRedirect('/login');
        $this->post('/login', ['login' => $this->dev->email, 'password' => 'password'])->assertRedirect($path);

        $this->get($path)->assertOk()->assertSee('Project two');
        $this->post($path, ['project' => (string) $this->otherProject->id])->assertOk();

        $res = $this->postJson('/api/auth/token', ['request_id' => $start['request_id'], 'secret' => $start['secret']])
            ->assertOk()->assertJsonPath('project.code', 'P2');
        $token = ApiToken::findValid($res->json('token'));
        $this->assertSame($this->otherProject->id, $token->project_id);
        $this->assertTrue($token->expires_at->between(now()->addDays(29), now()->addDays(31)));

        // Only once.
        $this->postJson('/api/auth/token', ['request_id' => $start['request_id'], 'secret' => $start['secret']])
            ->assertStatus(410)->assertJsonPath('error', 'already_used');
    }

    public function test_request_id_without_the_secret_gives_no_token(): void
    {
        $start = $this->postJson('/api/auth/start')->json();
        $path = parse_url($start['login_url'], PHP_URL_PATH);
        $this->actingAs($this->dev)->post($path, ['project' => (string) $this->project->id])->assertOk();

        $this->postJson('/api/auth/token', ['request_id' => $start['request_id'], 'secret' => 'wrong'])->assertStatus(404);
    }

    public function test_link_must_be_opened_within_one_minute(): void
    {
        $start = $this->postJson('/api/auth/start')->json();
        $path = parse_url($start['login_url'], PHP_URL_PATH);

        $this->travel(61)->seconds();
        $this->actingAs($this->dev)->get($path)->assertStatus(403)->assertSee(__('api.link_expired'));
        $this->postJson('/api/auth/token', ['request_id' => $start['request_id'], 'secret' => $start['secret']])
            ->assertStatus(410)->assertJsonPath('error', 'expired');
    }

    public function test_opened_in_time_can_finish_login_later(): void
    {
        $start = $this->postJson('/api/auth/start')->json();
        $path = parse_url($start['login_url'], PHP_URL_PATH);
        $this->get($path)->assertRedirect('/login'); // opened as a guest

        $this->travel(3)->minutes();
        $this->actingAs($this->dev)->post($path, ['project' => (string) $this->project->id])->assertOk();
        $this->postJson('/api/auth/token', ['request_id' => $start['request_id'], 'secret' => $start['secret']])->assertOk();
    }

    public function test_single_project_is_linked_without_asking(): void
    {
        $this->otherProject->members()->detach($this->dev->id);
        $start = $this->postJson('/api/auth/start')->json();
        $this->actingAs($this->dev)->get(parse_url($start['login_url'], PHP_URL_PATH))->assertOk()->assertSee(__('api.approved', ['project' => 'Project one', 'days' => 30]));

        $this->postJson('/api/auth/token', ['request_id' => $start['request_id'], 'secret' => $start['secret']])
            ->assertOk()->assertJsonPath('project.code', 'P1');
    }

    public function test_customer_cannot_approve(): void
    {
        $start = $this->postJson('/api/auth/start')->json();
        $path = parse_url($start['login_url'], PHP_URL_PATH);
        $this->actingAs($this->customer)->get($path)->assertStatus(403);
        $this->actingAs($this->customer)->post($path, ['project' => (string) $this->project->id])->assertStatus(403);
        $this->assertNull(ApiAuthRequest::first()->approved_at);
    }

    public function test_api_needs_a_valid_token(): void
    {
        $this->getJson('/api/tickets')->assertStatus(401)->assertJsonPath('error', 'auth_required');
        $this->api('nope')->getJson('/api/tickets')->assertStatus(401);

        $token = $this->login();
        $this->travel(31)->days();
        $this->api($token)->getJson('/api/tickets')->assertStatus(401)->assertJsonPath('error', 'auth_required');
    }

    public function test_create_ticket_with_only_a_title(): void
    {
        $token = $this->login();
        $res = $this->api($token)->postJson('/api/tickets', ['title' => 'Login is slow'])->assertCreated();

        $res->assertJsonPath('ticket.title', 'Login is slow')
            ->assertJsonPath('ticket.type', 'task')
            ->assertJsonPath('ticket.status', 'backlog')
            ->assertJsonPath('ticket.priority', 'medium')
            ->assertJsonPath('ticket.project.code', 'P1');
        $ticket = Ticket::firstWhere('number', $res->json('ticket.number'));
        $this->assertSame($this->dev->id, $ticket->reporter_id);
        $this->assertCount(1, $ticket->revisions);

        $this->api($token)->postJson('/api/tickets', [])->assertStatus(422)->assertJsonPath('error', 'validation');
    }

    public function test_create_ticket_with_labels_sprint_and_assignee(): void
    {
        Sprint::create(['project_id' => $this->project->id, 'number' => 3, 'name' => 'Checkout', 'status' => 'active']);
        $token = $this->login();

        $res = $this->api($token)->postJson('/api/tickets', [
            'title' => 'Crash on pay',
            'description' => "Line one\nline <two>\n\nSecond paragraph",
            'type' => 'باگ',
            'status' => 'In progress',
            'priority' => 'خیلی بالا',
            'sprint' => 'active',
            'assignee' => 'sara',
            'due_date' => '1405/07/10',
            'estimated_time' => '02:30',
        ])->assertCreated();

        $res->assertJsonPath('ticket.type', 'bug')
            ->assertJsonPath('ticket.status', 'in_progress')
            ->assertJsonPath('ticket.priority', 'highest')
            ->assertJsonPath('ticket.sprint', 3)
            ->assertJsonPath('ticket.assignee', 'Sara Dev')
            ->assertJsonPath('ticket.due_date', '2026-10-02')
            ->assertJsonPath('ticket.estimated_time', '02:30')
            ->assertJsonPath('ticket.description', "Line one\nline <two>\n\nSecond paragraph");

        $this->api($token)->postJson('/api/tickets', ['title' => 'x', 'type' => 'unknown'])
            ->assertStatus(422)->assertJsonPath('error', 'invalid_value');
    }

    public function test_new_ticket_is_assigned_to_the_developer_of_the_token(): void
    {
        $token = $this->login();
        $this->api($token)->postJson('/api/tickets', ['title' => 'Mine'])->assertCreated()->assertJsonPath('ticket.assignee', 'Sara Dev');
        $this->api($token)->postJson('/api/tickets', ['title' => 'Nobody', 'assignee' => 'none'])->assertCreated()->assertJsonPath('ticket.assignee', null);
        $this->api($token)->postJson('/api/tickets', ['title' => 'By id', 'assignee_id' => $this->dev->id])->assertCreated()->assertJsonPath('ticket.assignee', 'Sara Dev');

        // A token of an admin (not a developer): unassigned.
        [, $plain] = ApiToken::issue(User::factory()->admin()->create(), $this->project->id, 'admin bot');
        $this->api($plain)->postJson('/api/tickets', ['title' => 'Admin'])->assertCreated()->assertJsonPath('ticket.assignee', null);
    }

    public function test_list_filters_and_details(): void
    {
        $sprint = Sprint::create(['project_id' => $this->project->id, 'number' => 1, 'name' => 'One', 'status' => 'active']);
        $base = ['project_id' => $this->project->id, 'priority' => 'medium', 'reporter_id' => $this->dev->id];
        $a = Ticket::create($base + ['title' => 'Payment page error', 'type' => 'bug', 'status' => 'in_progress', 'sprint_id' => $sprint->id, 'content' => '<p>Gateway timeout</p>']);
        Ticket::create($base + ['title' => 'New report', 'type' => 'feature', 'status' => 'backlog']);
        Ticket::create(array_merge($base, ['project_id' => $this->otherProject->id, 'title' => 'Payment elsewhere', 'type' => 'bug', 'status' => 'backlog']));
        $a->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $token = $this->login();
        $api = fn ($q) => $this->api($token)->getJson('/api/tickets?'.http_build_query($q))->assertOk();

        $this->assertSame(2, $api([])->json('total'));
        $this->assertSame([$a->number], array_column($api(['title' => 'payment'])->json('tickets'), 'number'));
        $this->assertSame(1, $api(['description' => 'timeout'])->json('total'));
        $this->assertSame(1, $api(['type' => 'bug'])->json('total'));
        $this->assertSame(2, $api(['status' => 'in_progress,درصف انجام'])->json('total'));
        $this->assertSame(1, $api(['sprint' => '1'])->json('total'));
        $this->assertSame(1, $api(['sprint' => 'none'])->json('total'));
        $this->assertSame(1, $api(['date_from' => now()->subDays(2)->toDateString()])->json('total'));
        $this->assertSame(1, $api(['date_to' => now()->subDays(5)->toDateString()])->json('total'));

        // Token is linked to P1: another project is refused, and its tickets are not found.
        $this->api($token)->getJson('/api/tickets?project=P2')->assertStatus(422)->assertJsonPath('error', 'wrong_project');
        $other = Ticket::where('project_id', $this->otherProject->id)->first();
        $this->api($token)->getJson('/api/tickets/'.$other->number)->assertStatus(404);

        $this->api($token)->getJson('/api/tickets/'.$a->number)->assertOk()
            ->assertJsonPath('ticket.description', 'Gateway timeout')
            ->assertJsonPath('ticket.status_label', 'درحال انجام')
            ->assertSee('"status_label":"درحال انجام"', false) // plain UTF-8, not \u escapes
            ->assertSee('"url":"http', false);
    }

    public function test_token_without_project_needs_project_param(): void
    {
        $token = $this->login('none');
        $this->api($token)->getJson('/api/tickets')->assertStatus(422)->assertJsonPath('error', 'project_required');
        $this->api($token)->getJson('/api/me')->assertOk()->assertJsonCount(2, 'projects');
        $this->api($token)->getJson('/api/tickets?project=P2')->assertOk();
        $this->api($token)->postJson('/api/tickets', ['title' => 'Hi', 'project' => (string) $this->project->id])
            ->assertCreated()->assertJsonPath('ticket.project.code', 'P1');
        $this->api($token)->getJson('/api/options?project=P1')->assertOk()->assertJsonPath('assignees.0.name', 'Sara Dev');
    }

    public function test_revoked_token_stops_working(): void
    {
        $token = $this->login();
        $this->actingAs($this->dev)->get('/profile')->assertOk()->assertSee('Telegram bot');
        $id = ApiToken::findValid($token)->id;
        $this->actingAs($this->dev)->delete('/profile/api-tokens/'.$id)->assertRedirect();
        $this->api($token)->getJson('/api/me')->assertStatus(401);
    }
}
