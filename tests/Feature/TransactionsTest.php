<?php

namespace Tests\Feature;

use App\Models\GridPreference;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dev;

    private User $otherDev;

    private User $customer;

    private User $otherCustomer;

    private Project $project;

    private Project $project2;

    private Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->admin()->create();
        $this->dev = User::factory()->developer()->create(['locale' => 'en', 'calendar' => 'gregorian']);
        $this->otherDev = User::factory()->developer()->create();
        $this->customer = User::factory()->customer()->create(['locale' => 'en', 'calendar' => 'gregorian']);
        $this->otherCustomer = User::factory()->customer()->create();

        $this->project = Project::create(['code' => 'P1', 'name' => 'Project one']);
        $this->project2 = Project::create(['code' => 'P3', 'name' => 'Project three']);
        $this->otherProject = Project::create(['code' => 'P2', 'name' => 'Project two']);
        $this->project->members()->attach([$this->dev->id, $this->customer->id]);
        $this->project2->members()->attach([$this->dev->id, $this->customer->id]);
        $this->otherProject->members()->attach([$this->otherDev->id, $this->otherCustomer->id]);
    }

    private function ticket(array $extra = []): Ticket
    {
        return app(TicketService::class)->create(array_merge([
            'project_id' => $this->project->id, 'type' => 'task', 'priority' => 'medium', 'status' => 'done',
            'title' => 'Build the login page', 'cost' => 1000, 'due_date' => '2026-05-10',
        ], $extra), $this->dev);
    }

    private function payment(array $extra = []): Payment
    {
        return Payment::create(array_merge([
            'customer_id' => $this->customer->id, 'project_id' => $this->project->id,
            'amount' => 400, 'paid_on' => '2026-05-01', 'created_by' => $this->dev->id,
        ], $extra));
    }

    // ---- Tickets grid -----------------------------------------------------------

    public function test_tickets_grid_default_columns(): void
    {
        $this->ticket(['cost' => 250]);
        $html = $this->actingAs($this->dev)->get('/tickets')->assertOk()->getContent();

        foreach (['number', 'title', 'type', 'status', 'priority', 'sprint', 'cost'] as $col) {
            $this->assertMatchesRegularExpression('/<th data-col="'.$col.'" class="\s*"/', $html, "$col should be visible");
        }
        foreach (['project', 'assignee', 'due_date', 'updated_at', 'logged_time'] as $col) {
            $this->assertMatchesRegularExpression('/<th data-col="'.$col.'" class="d-none"/', $html, "$col should be hidden");
        }
    }

    public function test_user_column_choice_is_kept(): void
    {
        $this->actingAs($this->dev)->postJson('/grid-preferences', ['grid' => 'tickets', 'columns' => ['number', 'title', 'project']])->assertOk();
        $html = $this->get('/tickets')->getContent();
        $this->assertMatchesRegularExpression('/<th data-col="project" class="\s*"/', $html);
        $this->assertMatchesRegularExpression('/<th data-col="cost" class="d-none"/', $html);
        $this->assertSame(1, GridPreference::count());
    }

    public function test_tickets_totals_are_for_all_filtered_records_not_only_the_page(): void
    {
        foreach (range(1, 12) as $i) {
            $this->ticket(['cost' => 100, 'logged_minutes' => 30]);
        }
        $this->actingAs($this->dev);
        $this->postJson('/grid-preferences', ['grid' => 'tickets', 'columns' => ['number', 'title', 'cost', 'logged_time']]);

        $response = $this->get('/tickets?per_page=10')->assertOk();
        $response->assertSee('1,200');   // 12 × 100, although only 10 rows are on the page
        $response->assertSee('06:00');   // 12 × 30 minutes
        $this->assertStringNotContainsString('grid-totals d-none', $response->getContent());
    }

    public function test_totals_row_is_hidden_without_numeric_columns(): void
    {
        $this->ticket();
        $this->actingAs($this->dev)->postJson('/grid-preferences', ['grid' => 'tickets', 'columns' => ['number', 'title', 'status']]);
        $this->get('/tickets')->assertSee('grid-totals d-none', false);
    }

    // ---- Transactions -----------------------------------------------------------

    public function test_only_done_tickets_with_cost_and_due_date_are_costs(): void
    {
        $shown = $this->ticket(['title' => 'Counted ticket']);
        $this->ticket(['title' => 'Not done ticket', 'status' => 'testing']);
        $this->ticket(['title' => 'No cost ticket', 'cost' => null]);
        $this->ticket(['title' => 'No date ticket', 'due_date' => null]);
        $this->payment();

        $this->actingAs($this->dev)->get('/transactions')->assertOk()
            ->assertSee('Counted ticket')->assertDontSee('Not done ticket')
            ->assertDontSee('No cost ticket')->assertDontSee('No date ticket')
            ->assertViewHas('totals', ['payments' => 400.0, 'costs' => 1000.0, 'remaining' => 600.0]);

        // The developer moves the ticket back to testing: the cost disappears from the list and the totals.
        app(TicketService::class)->changeStatus($shown, \App\Enums\TicketStatus::Testing, $this->dev);
        $this->get('/transactions')->assertDontSee('Counted ticket')
            ->assertViewHas('totals', ['payments' => 400.0, 'costs' => 0.0, 'remaining' => -400.0]);

        // Back to done, then the cost is removed.
        app(TicketService::class)->changeStatus($shown, \App\Enums\TicketStatus::Done, $this->dev);
        $this->get('/transactions')->assertSee('Counted ticket');
        app(TicketService::class)->update($shown->fresh(), ['cost' => null], $this->dev);
        $this->get('/transactions')->assertDontSee('Counted ticket');
    }

    public function test_deleted_ticket_is_not_a_cost(): void
    {
        $ticket = $this->ticket(['title' => 'Deleted ticket']);
        app(TicketService::class)->delete($ticket, $this->dev);
        $this->actingAs($this->dev)->get('/transactions')->assertDontSee('Deleted ticket');
    }

    public function test_date_filter_limits_rows_and_totals(): void
    {
        $this->ticket(['title' => 'Phase one', 'due_date' => '2026-02-10', 'cost' => 500]);
        $this->ticket(['title' => 'Phase two', 'due_date' => '2026-06-10', 'cost' => 700]);
        $this->payment(['paid_on' => '2026-02-01', 'amount' => 100]);
        $this->payment(['paid_on' => '2026-06-01', 'amount' => 300]);

        $this->actingAs($this->dev)->get('/transactions?date_from=2026-06-01&date_to=2026-06-30')
            ->assertSee('Phase two')->assertDontSee('Phase one')
            ->assertViewHas('totals', ['payments' => 300.0, 'costs' => 700.0, 'remaining' => 400.0]);
    }

    public function test_amount_and_description_filters(): void
    {
        $this->payment(['amount' => 50, 'description' => 'Small one']);
        $this->payment(['amount' => 5000, 'description' => 'Big one']);

        $this->actingAs($this->dev)->get('/transactions?amount_from=1,000&kind[]=payment')
            ->assertSee('Big one')->assertDontSee('Small one');
        $this->get('/transactions?q=Small')->assertSee('Small one')->assertDontSee('Big one');
    }

    public function test_summary_by_project_when_more_than_one_project(): void
    {
        $this->ticket(['project_id' => $this->project->id, 'cost' => 100]);
        $this->ticket(['project_id' => $this->project2->id, 'cost' => 200]);

        $this->actingAs($this->dev)->get('/transactions?project[]='.$this->project->id)
            ->assertViewHas('byProject', []);

        $response = $this->get('/transactions?project[]='.$this->project->id.'&project[]='.$this->project2->id);
        $byProject = $response->viewData('byProject');
        $this->assertCount(2, $byProject);
        $response->assertSee(__('transactions.by_project'));
    }

    public function test_customer_sees_only_own_transactions_read_only(): void
    {
        $this->payment(['description' => 'My payment']);
        $this->payment(['customer_id' => $this->otherCustomer->id, 'project_id' => $this->otherProject->id, 'description' => 'Other payment']);
        $this->ticket(['title' => 'My project cost']);
        $this->ticket(['title' => 'Other project cost', 'project_id' => $this->otherProject->id]);

        $this->actingAs($this->customer)->get('/transactions?customer_id='.$this->otherCustomer->id)->assertOk()
            ->assertSee('My payment')->assertSee('My project cost')
            ->assertDontSee('Other payment')->assertDontSee('Other project cost')
            ->assertDontSee(route('payments.create'));

        $this->get('/payments/create')->assertForbidden();
        $this->post('/payments', ['customer_id' => $this->customer->id, 'amount' => 1, 'paid_on' => '2026-01-01'])->assertForbidden();
    }

    public function test_developer_adds_payment_with_validation(): void
    {
        $this->actingAs($this->dev);

        // Customer, amount and date are required.
        $this->post('/payments', [])->assertSessionHasErrors(['customer_id', 'amount', 'paid_on']);
        // No decimal point.
        $this->post('/payments', ['customer_id' => $this->customer->id, 'amount' => '10.5', 'paid_on' => '2026-01-01'])->assertSessionHasErrors('amount');
        // Not my customer.
        $this->post('/payments', ['customer_id' => $this->otherCustomer->id, 'amount' => '10', 'paid_on' => '2026-01-01'])->assertSessionHasErrors('customer_id');
        // A project the customer is not on.
        $this->post('/payments', ['customer_id' => $this->customer->id, 'project_id' => $this->otherProject->id, 'amount' => '10', 'paid_on' => '2026-01-01'])->assertSessionHasErrors('project_id');

        $this->post('/payments', ['customer_id' => $this->customer->id, 'amount' => '1,250,000', 'paid_on' => '2026-01-01', 'description' => 'First'])
            ->assertRedirect('/transactions');
        $payment = Payment::sole();
        $this->assertSame(1250000, $payment->amount);
        $this->assertNull($payment->project_id);
        $this->assertSame($this->dev->id, $payment->created_by);
    }

    public function test_any_developer_of_the_customer_can_edit_and_delete(): void
    {
        $payment = $this->payment(['created_by' => $this->admin->id]);

        $this->actingAs($this->otherDev)->get("/payments/{$payment->id}/edit")->assertForbidden();
        $this->delete("/payments/{$payment->id}")->assertForbidden();

        // $this->dev did not create it, but the customer is on the developer's project.
        $this->actingAs($this->dev)->get("/payments/{$payment->id}/edit")->assertOk();
        $this->put("/payments/{$payment->id}", ['customer_id' => $this->customer->id, 'project_id' => $this->project2->id, 'amount' => '900', 'paid_on' => '2026-03-03'])
            ->assertRedirect('/transactions');
        $this->assertSame(900, $payment->fresh()->amount);
        $this->delete("/payments/{$payment->id}")->assertRedirect('/transactions');
        $this->assertSoftDeleted($payment);
    }

    public function test_developer_does_not_see_payments_of_other_developers_projects(): void
    {
        // The customer is on this developer's project AND on another developer's project.
        $this->otherProject->members()->attach($this->customer->id);
        $foreign = $this->payment(['project_id' => $this->otherProject->id, 'description' => 'Foreign project payment']);
        $this->payment(['project_id' => null, 'description' => 'Payment without project']);

        $this->actingAs($this->dev)->get('/transactions')
            ->assertSee('Payment without project')->assertDontSee('Foreign project payment');
        $this->get("/payments/{$foreign->id}/edit")->assertForbidden();

        $this->actingAs($this->otherDev)->get('/transactions')->assertSee('Foreign project payment');
        $this->actingAs($this->customer)->get('/transactions')->assertSee('Foreign project payment');
    }

    public function test_admin_sees_all_transactions_read_only(): void
    {
        $payment = $this->payment(['description' => 'Paid by card']);
        $this->ticket(['title' => 'Cost in P1']);

        $html = $this->actingAs($this->admin)->get('/transactions')->assertOk()
            ->assertSee('Paid by card')->assertSee('Cost in P1')->getContent();
        $this->assertStringNotContainsString(route('payments.create'), $html);
        $this->assertStringNotContainsString(route('payments.edit', $payment), $html);
        $this->assertStringNotContainsString('data-col="actions"', $html);

        $this->get('/payments/create')->assertForbidden();
        $this->post('/payments', ['customer_id' => $this->customer->id, 'amount' => '100', 'paid_on' => '2026-03-03'])->assertForbidden();
        $this->get("/payments/{$payment->id}/edit")->assertForbidden();
        $this->put("/payments/{$payment->id}", ['customer_id' => $this->customer->id, 'amount' => '1', 'paid_on' => '2026-03-03'])->assertForbidden();
        $this->delete("/payments/{$payment->id}")->assertForbidden();
        $this->assertSame(400, $payment->fresh()->amount);
        $this->assertSame(1, Payment::count());
    }

    public function test_staff_filter_by_customer_uses_customer_projects_for_costs(): void
    {
        $this->ticket(['title' => 'Cost in P1']);
        $this->ticket(['title' => 'Cost in P2', 'project_id' => $this->otherProject->id]);

        $this->actingAs($this->admin)->get('/transactions?customer_id='.$this->customer->id)
            ->assertSee('Cost in P1')->assertDontSee('Cost in P2');
        $this->get('/transactions')->assertSee('Cost in P1')->assertSee('Cost in P2');
    }
}
