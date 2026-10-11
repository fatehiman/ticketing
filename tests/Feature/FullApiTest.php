<?php

namespace Tests\Feature;

use App\Models\FullApiKey;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullApiTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private Project $p1;

    private Project $p2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->dev = User::factory()->developer()->create(['first_name' => 'Sara', 'last_name' => 'Dev']);
        $this->p1 = Project::create(['code' => 'P1', 'name' => 'Project one']);
        $this->p2 = Project::create(['code' => 'P2', 'name' => 'Project two']);
        $this->p1->members()->attach($this->dev->id);
        $this->p2->members()->attach($this->dev->id);
    }

    private function key(?User $user = null): string
    {
        return FullApiKey::rotate($user ?? $this->dev)[1];
    }

    private function api(?string $key = null): static
    {
        return $this->withHeader('Authorization', 'Bearer '.($key ?? $this->key()));
    }

    public function test_profile_creates_and_rotates_the_key(): void
    {
        $this->actingAs($this->dev)->post('/profile/full-api-key')->assertSessionHas('full_api_key');
        $first = FullApiKey::where('user_id', $this->dev->id)->first();
        $this->assertNotNull($first);

        $this->actingAs($this->dev)->post('/profile/full-api-key');
        $this->assertSame(1, FullApiKey::count());
        $this->assertNotSame($first->key_hash, FullApiKey::first()->key_hash);

        $this->actingAs($this->dev)->get('/profile')->assertOk()->assertSee('tkf_…');
        $this->actingAs($this->dev)->delete('/profile/full-api-key');
        $this->assertSame(0, FullApiKey::count());
    }

    public function test_customer_has_no_key_and_old_key_stops_after_rotate(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->post('/profile/full-api-key')->assertForbidden();

        $old = $this->key();
        $this->api($old)->getJson('/api/full/me')->assertOk();
        $this->key(); // rotate
        $this->withHeader('Authorization', 'Bearer '.$old)->getJson('/api/full/me')->assertStatus(401)->assertJsonPath('error', 'auth_required');
    }

    public function test_bot_token_and_full_key_do_not_mix(): void
    {
        $this->getJson('/api/full/me')->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer tkt_x')->getJson('/api/full/me')->assertStatus(401);
        $this->api()->getJson('/api/me')->assertStatus(401); // a full key is not a bot token
    }

    public function test_project_is_mandatory_where_needed_and_accepts_id_code_or_name(): void
    {
        $api = $this->api();
        $api->getJson('/api/full/tickets')->assertStatus(422)->assertJsonPath('error', 'project_required');
        $api->postJson('/api/full/tickets', ['title' => 'x'])->assertStatus(422)->assertJsonPath('error', 'project_required');
        $api->getJson('/api/full/options')->assertStatus(422)->assertJsonPath('error', 'project_required');

        foreach ([(string) $this->p1->id, 'P1', 'project one'] as $value) {
            $api->postJson('/api/full/tickets', ['title' => "via {$value}", 'project' => $value])
                ->assertCreated()->assertJsonPath('ticket.project.code', 'P1');
        }
        $api->postJson('/api/full/tickets', ['title' => 'x', 'project' => 'NOPE'])->assertNotFound()->assertJsonPath('error', 'project_not_found');

        $api->getJson('/api/full/tickets?project=P2')->assertOk()->assertJsonPath('total', 0);
        $api->getJson('/api/full/tickets?project=P1')->assertOk()->assertJsonPath('total', 3);
    }

    public function test_user_sees_only_own_projects(): void
    {
        $other = Project::create(['code' => 'X1', 'name' => 'Not mine']);
        $api = $this->api();
        $api->getJson('/api/full/tickets?project=X1')->assertNotFound();
        $api->getJson('/api/full/projects')->assertOk()->assertJsonCount(2, 'projects');
        $api->getJson('/api/full/projects/'.$other->id)->assertNotFound();
    }

    public function test_ticket_read_update_delete_by_number(): void
    {
        $api = $this->api();
        $number = $api->postJson('/api/full/tickets', ['title' => 'First', 'project' => 'P1'])->assertCreated()->json('ticket.number');

        $api->getJson("/api/full/tickets/{$number}")->assertOk()->assertJsonPath('ticket.title', 'First');
        $api->getJson("/api/full/tickets/{$number}?project=P2")->assertStatus(422)->assertJsonPath('error', 'wrong_project');

        $api->patchJson("/api/full/tickets/{$number}", ['status' => 'in_progress', 'priority' => 'high', 'cost' => 5000, 'logged_time' => '01:30'])
            ->assertOk()->assertJsonPath('ticket.status', 'in_progress')->assertJsonPath('ticket.priority', 'high')
            ->assertJsonPath('ticket.cost', 5000)->assertJsonPath('ticket.logged_time', '01:30');
        $this->assertSame('First', Ticket::where('number', $number)->first()->title); // untouched field stays

        $api->patchJson("/api/full/tickets/{$number}", ['assignee' => 'none'])->assertOk()->assertJsonPath('ticket.assignee', null);
        $api->patchJson("/api/full/tickets/{$number}", [])->assertStatus(422);
        $api->patchJson("/api/full/tickets/{$number}", ['status' => 'bogus'])->assertStatus(422)->assertJsonPath('error', 'invalid_value');

        $api->deleteJson("/api/full/tickets/{$number}")->assertOk();
        $api->getJson("/api/full/tickets/{$number}")->assertNotFound();
        $this->assertSoftDeleted('tickets', ['number' => $number]);
    }

    public function test_project_create_read_update(): void
    {
        $api = $this->api();
        $api->postJson('/api/full/projects', ['name' => 'Shop'])->assertStatus(422)->assertJsonPath('error', 'validation');
        $api->postJson('/api/full/projects', ['name' => 'Shop', 'code' => 'p1'])->assertStatus(422); // code is taken (case-insensitive)

        $api->postJson('/api/full/projects', ['name' => 'Shop', 'code' => 'shop', 'budget' => '1,500,000'])
            ->assertCreated()->assertJsonPath('project.code', 'SHOP')->assertJsonPath('project.budget', 1500000)
            ->assertJsonPath('project.developers.0.id', $this->dev->id);

        $api->patchJson('/api/full/projects/SHOP', ['status' => 'on_hold'])->assertOk()->assertJsonPath('project.status', 'on_hold')
            ->assertJsonPath('project.name', 'Shop');
        $api->getJson('/api/full/projects/Shop')->assertOk()->assertJsonPath('project.code', 'SHOP');
        $api->deleteJson('/api/full/projects/SHOP')->assertStatus(405);
        $api->postJson('/api/full/tickets', ['title' => 'In new project', 'project' => 'SHOP'])->assertCreated();
    }

    public function test_admin_can_manage_projects_but_not_write_tickets(): void
    {
        $admin = User::factory()->admin()->create();
        $number = $this->api()->postJson('/api/full/tickets', ['title' => 'Dev ticket', 'project' => 'P1'])->json('ticket.number');
        $api = $this->api($this->key($admin)); // the test client keeps one header: set the admin key after the developer call

        $api->postJson('/api/full/projects', ['name' => 'Admin made', 'code' => 'ADM', 'developers' => [$this->dev->id]])
            ->assertCreated()->assertJsonPath('project.developers.0.id', $this->dev->id);
        $api->getJson('/api/full/tickets?project=ADM')->assertOk();
        $api->postJson('/api/full/tickets', ['title' => 'x', 'project' => 'ADM'])->assertStatus(403)->assertJsonPath('error', 'forbidden');

        $api->patchJson("/api/full/tickets/{$number}", ['status' => 'done'])->assertStatus(403);
        $api->deleteJson("/api/full/tickets/{$number}")->assertStatus(403);
    }
}
