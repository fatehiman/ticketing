<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Mail\BillIssued;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use App\Sms\Sms;
use App\Support\Bills;
use App\Support\Dates;
use App\Support\Money;
use App\Support\ProjectContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bills (صورتحساب). A developer issues a bill to a customer of a project: picked done tickets
 * that are not on another bill (with or without a cost — tickets without a cost are listed for
 * the record, and a manual item like "cost of phase 1" carries their price), plus manual items
 * (saved as done tickets). A sprint can be picked to tick all of its done tickets at once.
 * The customer can get an SMS and / or an email about the new bill.
 */
class BillController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $f = [
            'status' => in_array($request->query('status'), Bills::STATUSES, true) ? $request->query('status') : null,
            'project_id' => $request->integer('project_id') ?: null,
            'customer_id' => $user->isStaff() ? ($request->integer('customer_id') ?: null) : null,
        ];

        $bills = Bill::visibleTo($user)->with(['project', 'customer'])
            ->when($f['project_id'], fn ($q, $id) => $q->where('project_id', $id))
            ->when($f['customer_id'], fn ($q, $id) => $q->where('customer_id', $id))
            ->orderByDesc('issued_on')->orderByDesc('id')->get();

        // Paid amounts come from all bills and payments of these customers (oldest bill first).
        $paid = Bills::allocate($bills->pluck('customer_id')->all());
        $bills->each(function (Bill $bill) use ($paid) {
            $bill->paid = (int) ($paid[$bill->id] ?? 0);
            $bill->status = Bills::status($bill->total, $bill->paid);
        });
        $totals = ['total' => $bills->sum('total'), 'paid' => $bills->sum('paid')];
        if ($f['status']) {
            $bills = $bills->where('status', $f['status'])->values();
        }

        $page = max(1, $request->integer('page', 1));
        $rows = new LengthAwarePaginator($bills->forPage($page, 25)->values(), $bills->count(), 25, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('bills.index', [
            'rows' => $rows,
            'filters' => $f,
            'totals' => $totals,
            'projects' => Project::query()->visibleTo($user)->orderBy('name')->get(),
            'customers' => $user->isStaff() ? TransactionController::customerOptions($user) : collect(),
            'debt' => $user->isCustomer() ? Bills::debtOf($user) : null,
        ]);
    }

    public function create(Request $request, ProjectContext $context)
    {
        $this->authorize('create', Bill::class);
        $user = $request->user();
        $projects = $this->projectOptions($user);

        return view('bills.create', [
            'projects' => $projects,
            'projectId' => $request->integer('project_id') ?: $context->current()?->id,
            'customerId' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Bill::class);
        $user = $request->user();
        $data = $this->validated($request, $user);

        $bill = DB::transaction(function () use ($data, $user) {
            $bill = Bill::create([
                'project_id' => $data['project_id'],
                'customer_id' => $data['customer_id'],
                'issued_on' => $data['issued_on'],
                'due_on' => $data['due_on'],
                'description' => $data['description'],
                'created_by' => $user->id,
            ]);

            $sort = 0;
            foreach (Ticket::whereIn('id', $data['tickets'])->orderBy('number')->get() as $ticket) {
                $bill->items()->create(['ticket_id' => $ticket->id, 'title' => $ticket->title, 'amount' => (int) $ticket->cost, 'sort_order' => ++$sort]);
            }
            // A manual item is saved as a done ticket with that cost, so it is a cost on the transactions page too.
            foreach ($data['items'] as $item) {
                $ticket = $this->tickets->create([
                    'project_id' => $bill->project_id,
                    'type' => 'task',
                    'priority' => 'medium',
                    'status' => TicketStatus::Done->value,
                    'title' => $item['title'],
                    'content' => $item['details'] ? '<p>'.nl2br(e($item['details'])).'</p>' : null,
                    'cost' => $item['amount'],
                    'due_date' => $bill->issued_on->toDateString(),
                    'assignee_id' => $user->id,
                ], $user);
                $bill->items()->create([
                    'ticket_id' => $ticket->id, 'title' => $item['title'], 'details' => $item['details'],
                    'amount' => $item['amount'], 'is_manual' => true, 'sort_order' => ++$sort,
                ]);
            }

            $bill->update(['total' => $bill->items()->sum('amount')]);

            return $bill;
        });

        $sent = $this->notify($bill->fresh(['customer']), $request->boolean('notify_sms'), $request->boolean('notify_email'));

        return redirect()->route('bills.show', $bill)
            ->with('success', __('bills.saved', ['number' => $bill->number]).($sent ? ' '.__('bills.notified', ['by' => implode(' + ', $sent)]) : ''));
    }

    public function show(Request $request, Bill $bill)
    {
        $this->authorize('view', $bill);
        $bill->load(['project', 'customer', 'creator', 'items.ticket', 'payments' => fn ($q) => $q->latest('paid_on')]);
        $bill->paid = (int) (Bills::allocate([$bill->customer_id])[$bill->id] ?? 0);
        $bill->status = Bills::status($bill->total, $bill->paid);

        return view('bills.show', [
            'bill' => $bill,
            'debt' => Bills::debtOf($bill->customer),
        ]);
    }

    public function destroy(Request $request, Bill $bill)
    {
        $this->authorize('delete', $bill);
        $user = $request->user();

        DB::transaction(function () use ($bill, $user) {
            // Manual items were made only for this bill: delete their tickets too (soft delete, with history).
            $bill->items()->where('is_manual', true)->with('ticket')->get()
                ->each(fn (BillItem $item) => $item->ticket && ! $item->ticket->trashed() ? $this->tickets->delete($item->ticket, $user) : null);
            $bill->delete();
        });

        return redirect()->route('bills.index')->with('success', __('app.deleted'));
    }

    /** SMS and / or email to the customer. Both are sent by the queue worker. */
    private function notify(Bill $bill, bool $sms, bool $email): array
    {
        $customer = $bill->customer;
        $sent = [];
        if ($sms && $customer->mobile && Sms::send($customer->mobile, 'bill_issued', [$bill->number])) {
            $sent[] = __('bills.by_sms');
        }
        if ($email && $customer->email) {
            Mail::to($customer)->locale($customer->locale ?: config('app.locale'))->send(new BillIssued($bill));
            $sent[] = __('bills.by_email');
        }

        return $sent;
    }

    /**
     * Projects of the developer with their customers, the tickets that can go on a bill
     * (done and not on another, not deleted, bill) and the sprints of those tickets.
     */
    private function projectOptions(User $user)
    {
        $projects = Project::query()->visibleTo($user)->with(['customers' => fn ($q) => $q->where('is_active', true)->orderBy('first_name')])
            ->orderBy('name')->get();
        $tickets = self::billableTickets($projects->pluck('id')->all())->with('sprint')->get()->groupBy('project_id');

        return $projects->map(fn (Project $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'currency' => __('app.currency.'.($p->currency ?: 'IRT')),
            'customers' => $p->customers->map(fn (User $c) => [
                'id' => $c->id, 'name' => $c->name, 'email' => (bool) $c->email, 'mobile' => (bool) $c->mobile,
            ])->values(),
            'tickets' => ($tickets[$p->id] ?? collect())->map(fn (Ticket $t) => [
                'id' => $t->id, 'number' => $t->number, 'title' => $t->title, 'cost' => (int) $t->cost,
                'date' => Dates::format($t->due_date ?? $t->resolved_at),
                'sprint_id' => $t->sprint_id, 'sprint' => $t->sprint?->label(),
            ])->values(),
            // Only sprints that have billable tickets, newest first.
            'sprints' => ($tickets[$p->id] ?? collect())->pluck('sprint')->filter()->unique('id')
                ->sortByDesc('number')->map(fn (Sprint $s) => [
                    'id' => $s->id,
                    'name' => $s->label(),
                    'count' => $tickets[$p->id]->where('sprint_id', $s->id)->count(),
                    'current' => $s->isCurrent(),
                ])->values(),
        ])->values();
    }

    public static function billableTickets(array $projectIds)
    {
        return Ticket::whereIn('project_id', $projectIds ?: [0])
            ->where('status', TicketStatus::Done->value)
            ->whereNotIn('id', BillItem::whereNotNull('ticket_id')->whereHas('bill')->select('ticket_id'))
            ->orderByDesc('number');
    }

    private function validated(Request $request, User $user): array
    {
        $projectIds = Project::query()->visibleTo($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $project = in_array($request->integer('project_id'), $projectIds, true) ? Project::find($request->integer('project_id')) : null;
        $customerIds = $project ? $project->customers()->pluck('users.id')->map(fn ($id) => (int) $id)->all() : [];
        $ticketIds = $project ? self::billableTickets([$project->id])->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        // Manual item rows: drop the empty ones, read amounts like "1,250,000".
        $items = collect($request->input('items', []))
            ->filter(fn ($i) => is_array($i))
            ->map(fn ($i) => [
                'title' => trim((string) ($i['title'] ?? '')),
                'amount' => Money::parse($i['amount'] ?? null),
                'details' => trim((string) ($i['details'] ?? '')) ?: null,
            ])
            ->reject(fn ($i) => $i['title'] === '' && $i['amount'] === null && $i['details'] === null)
            ->values()->all();
        $request->merge(['items' => $items]);

        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::in($projectIds)],
            'customer_id' => ['required', 'integer', Rule::in($customerIds)],
            'issued_on' => ['required', 'string', fn ($a, $v, $fail) => Dates::isValid($v) ? null : $fail(__('validation.date', ['attribute' => __('bills.fields.issued_on')]))],
            'due_on' => ['nullable', 'string', fn ($a, $v, $fail) => Dates::isValid($v) ? null : $fail(__('validation.date', ['attribute' => __('bills.fields.due_on')]))],
            'description' => ['nullable', 'string', 'max:1000'],
            'tickets' => ['nullable', 'array'],
            'tickets.*' => ['integer', Rule::in($ticketIds)],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.title' => ['required', 'string', 'max:250'],
            'items.*.amount' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'items.*.details' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'project_id' => __('bills.fields.project'),
            'customer_id' => __('bills.fields.customer'),
            'issued_on' => __('bills.fields.issued_on'),
            'due_on' => __('bills.fields.due_on'),
            'description' => __('bills.fields.description'),
            'tickets.*' => __('bills.fields.tickets'),
            'items.*.title' => __('bills.fields.item_title'),
            'items.*.amount' => __('bills.fields.amount'),
            'items.*.details' => __('bills.fields.item_details'),
        ]);

        $data['tickets'] = array_values(array_unique(array_map('intval', $data['tickets'] ?? [])));
        $data['items'] = $data['items'] ?? [];
        if (! $data['tickets'] && ! $data['items']) {
            throw ValidationException::withMessages(['items' => __('bills.need_item')]);
        }
        $data['issued_on'] = Dates::parse($data['issued_on'])->toDateString();
        $data['due_on'] = ! empty($data['due_on']) ? Dates::parse($data['due_on'])->toDateString() : null;
        $data['description'] = isset($data['description']) ? Str::limit($data['description'], 1000, '') : null;

        return $data;
    }
}
