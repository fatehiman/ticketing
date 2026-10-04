<?php

namespace Tests\Feature;

use App\Mail\BillIssued;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Project;
use App\Models\SmsMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use App\Support\Bills;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillsTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private User $customer;

    private User $otherCustomer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Mail::fake();

        $this->dev = User::factory()->developer()->create([
            'locale' => 'en', 'calendar' => 'gregorian', 'card_number' => '6037991234567890', 'iban' => 'IR120570000000000000000001', 'account_holder' => 'Dev One',
        ]);
        $this->customer = User::factory()->customer()->create(['locale' => 'en', 'calendar' => 'gregorian', 'mobile' => '09121112222']);
        $this->otherCustomer = User::factory()->customer()->create();
        $this->project = Project::create(['code' => 'P1', 'name' => 'Project one', 'currency' => 'IRT']);
        $this->project->members()->attach([$this->dev->id, $this->customer->id, $this->otherCustomer->id]);
    }

    private function doneTicket(int $cost = 1000, string $title = 'Login page'): Ticket
    {
        return app(TicketService::class)->create([
            'project_id' => $this->project->id, 'type' => 'task', 'priority' => 'medium', 'status' => 'done',
            'title' => $title, 'cost' => $cost, 'due_date' => '2026-09-01',
        ], $this->dev);
    }

    private function issue(array $extra = [])
    {
        return $this->actingAs($this->dev)->post('/bills', array_merge([
            'project_id' => $this->project->id, 'customer_id' => $this->customer->id, 'issued_on' => '2026-10-01',
        ], $extra));
    }

    public function test_developer_issues_a_bill_with_tickets_and_manual_items(): void
    {
        $ticket = $this->doneTicket(1000);
        $this->doneTicket(0, 'No cost'); // not billable
        $this->actingAs($this->dev)->get('/bills/create')->assertOk()->assertSee('Login page')->assertDontSee('No cost');

        $this->issue([
            'tickets' => [$ticket->id],
            'items' => [
                ['title' => 'Support October', 'amount' => '2,500,000', 'details' => 'Monthly support'],
                ['title' => '', 'amount' => '', 'details' => ''], // empty row is ignored
            ],
            'notify_sms' => '1', 'notify_email' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $bill = Bill::with('items')->firstOrFail();
        $this->assertSame(1001, (int) $bill->number);
        $this->assertSame(2501000, $bill->total);
        $this->assertCount(2, $bill->items);

        // The manual item is a done ticket with that cost → a cost on the transactions page.
        $manual = Ticket::where('title', 'Support October')->firstOrFail();
        $this->assertSame('done', $manual->status->value);
        $this->assertSame(2500000, $manual->cost);
        $this->assertSame(2501000, Bills::debtOf($this->customer));

        // The customer is told by SMS (bill number) and email.
        $sms = SmsMessage::where('purpose', 'bill_issued')->firstOrFail();
        $this->assertSame(['+989121112222', 24562, [1001]], [$sms->mobile, $sms->template_id, $sms->params]);
        Mail::assertQueued(BillIssued::class, fn (BillIssued $m) => $m->hasTo($this->customer->email));
        $html = (new BillIssued($bill))->render();
        $this->assertStringContainsString('Support October', $html);
        $this->assertStringContainsString('IR120570000000000000000001', $html);

        // A ticket on a bill cannot go on another bill.
        $this->actingAs($this->dev)->get('/bills/create')->assertDontSee('Login page');
        $this->issue(['tickets' => [$ticket->id]])->assertSessionHasErrors('tickets.0');
    }

    public function test_bill_needs_an_item_and_a_customer_of_the_project(): void
    {
        $this->issue()->assertSessionHasErrors('items');
        $stranger = User::factory()->customer()->create();
        $this->issue(['customer_id' => $stranger->id, 'items' => [['title' => 'X', 'amount' => '10']]])->assertSessionHasErrors('customer_id');
        $this->issue(['items' => [['title' => 'X', 'amount' => '10.5']]])->assertSessionHasErrors('items.0.amount');
        $this->issue(['items' => [['title' => 'X', 'amount' => '10']]])->assertSessionHasNoErrors();
        Mail::assertNothingQueued(); // no box ticked
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_who_sees_bills(): void
    {
        $this->issue(['items' => [['title' => 'Mine', 'amount' => '10']]]);
        $bill = Bill::firstOrFail();

        $this->actingAs($this->customer)->get('/bills')->assertOk()->assertSee('#1001');
        $this->get('/bills/1001')->assertOk()->assertSee('Mine')->assertSee('6037')->assertSee(route('payments.create', ['bill' => 1001]));
        $this->get('/bills/create')->assertForbidden();
        $this->post('/bills', [])->assertForbidden();
        $this->delete('/bills/1001')->assertForbidden();

        $this->actingAs($this->otherCustomer)->get('/bills/1001')->assertForbidden();
        $this->get('/bills')->assertDontSee('#1001');

        $this->actingAs(User::factory()->admin()->create())->get('/bills/1001')->assertOk()->assertDontSee(route('bills.destroy', $bill));
    }

    public function test_payments_pay_bills_oldest_first(): void
    {
        $this->issue(['items' => [['title' => 'A', 'amount' => '1000']], 'issued_on' => '2026-09-01']);
        $this->issue(['items' => [['title' => 'B', 'amount' => '800']], 'issued_on' => '2026-10-01']);
        [$a, $b] = Bill::orderBy('id')->get()->all();

        Payment::create(['customer_id' => $this->customer->id, 'amount' => 1500, 'paid_on' => '2026-10-02', 'created_by' => $this->dev->id]);
        Payment::create(['customer_id' => $this->customer->id, 'amount' => 9999, 'paid_on' => '2026-10-02', 'status' => 'pending', 'created_by' => $this->customer->id]);

        $paid = Bills::allocate([$this->customer->id]);
        $this->assertSame([1000, 500], [$paid[$a->id], $paid[$b->id]]);
        $this->assertSame('paid', Bills::status(1000, 1000));
        $this->assertSame('partial', Bills::status(800, 500));
        $this->assertSame(300, Bills::debtOf($this->customer)); // pending voucher does not count

        $this->actingAs($this->customer)->get('/bills?status=partial')->assertSee('#'.$b->number)->assertDontSee('#'.$a->number.'<');
    }

    public function test_customer_voucher_is_reviewed_by_the_developer(): void
    {
        $this->issue(['items' => [['title' => 'A', 'amount' => '1000']]]);

        // The form suggests the whole debt and shows the developer's bank details.
        $this->actingAs($this->customer)->get('/payments/create?bill=1001')->assertOk()
            ->assertSee('value="1,000"', false)->assertSee('IR120570000000000000000001');

        $this->post('/payments', ['amount' => '1,200', 'paid_on' => '2026-10-03'])->assertSessionHasErrors(['paid_time', 'reference_no']);
        $this->post('/payments', ['amount' => '1,200', 'paid_on' => '2026-10-03', 'paid_time' => '9:05', 'reference_no' => '۱۲۳۴۵', 'bill_id' => Bill::first()->id])
            ->assertRedirect('/payments');
        $voucher = Payment::firstOrFail();
        $this->assertSame(['pending', '09:05', '12345', $this->project->id], [$voucher->status, $voucher->paid_time, $voucher->reference_no, $voucher->project_id]);
        $this->assertSame(1000, Bills::debtOf($this->customer));

        // The customer may change it while it waits.
        $this->get("/payments/{$voucher->id}/edit")->assertOk();
        $this->post("/payments/{$voucher->id}/review", ['status' => 'accepted'])->assertForbidden();

        // The developer accepts it: now it is a payment, the debt is −200 (prepaid).
        $this->actingAs($this->dev)->get('/payments')->assertOk()->assertSee('12345');
        $this->post("/payments/{$voucher->id}/review", ['status' => 'accepted'])->assertSessionHas('success');
        $this->assertSame('accepted', $voucher->fresh()->status);
        $this->assertSame(-200, Bills::debtOf($this->customer));
        $this->get('/transactions')->assertSee('1,200');

        // Accepted: the customer cannot change or delete it any more.
        $this->actingAs($this->customer)->get("/payments/{$voucher->id}/edit")->assertForbidden();
        $this->delete("/payments/{$voucher->id}")->assertForbidden();

        // The developer can decline and delete it, and add a voucher for the customer.
        $this->actingAs($this->dev)->post("/payments/{$voucher->id}/review", ['status' => 'declined']);
        $this->assertSame(1000, Bills::debtOf($this->customer));
        $this->delete("/payments/{$voucher->id}")->assertRedirect();
        $this->assertSoftDeleted($voucher);
        $this->post('/payments', ['customer_id' => $this->customer->id, 'amount' => '400', 'paid_on' => '2026-10-04', 'reference_no' => '999'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', Payment::latest('id')->first()->status);
    }

    public function test_deleting_a_bill_deletes_its_manual_tickets_only(): void
    {
        $picked = $this->doneTicket(500);
        $this->issue(['tickets' => [$picked->id], 'items' => [['title' => 'Manual', 'amount' => '100']]]);

        $this->actingAs($this->dev)->delete('/bills/1001')->assertRedirect('/bills');
        $this->assertSoftDeleted(Bill::withTrashed()->first());
        $this->assertSoftDeleted(Ticket::withTrashed()->where('title', 'Manual')->first());
        $this->assertNotSoftDeleted($picked);
        // The picked ticket can go on a new bill again.
        $this->get('/bills/create')->assertSee('Login page');
    }

    public function test_developer_saves_bank_details_in_profile(): void
    {
        $this->actingAs($this->dev)->put('/profile', ['locale' => 'en', 'calendar' => 'gregorian', 'card_number' => '6037-9912-3456', 'iban' => 'IR12'])
            ->assertSessionHasErrors(['card_number', 'iban']);
        $this->put('/profile', ['locale' => 'en', 'calendar' => 'gregorian', 'card_number' => '6037 9912 3456 7899', 'iban' => 'ir12 0570 0000 0000 0000 0000 02', 'account_holder' => 'Ali'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['6037991234567899', 'IR120570000000000000000002'], [$this->dev->fresh()->card_number, $this->dev->fresh()->iban]);

        // Customers have no bank fields.
        $this->actingAs($this->customer)->put('/profile', ['locale' => 'en', 'calendar' => 'gregorian', 'card_number' => '6037991234567899']);
        $this->assertNull($this->customer->fresh()->card_number);
    }
}
