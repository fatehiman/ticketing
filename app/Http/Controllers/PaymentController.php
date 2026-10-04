<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Support\Bills;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Payments (bank vouchers). There is no payment gateway: the customer pays to the card / IBAN
 * of the developer and registers the voucher here (pending). A developer accepts, declines,
 * edits or deletes it, or registers a payment for the customer (accepted at once).
 * Only accepted payments count on the transactions page.
 */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = in_array($request->query('status'), Payment::STATUSES, true) ? $request->query('status') : null;
        $customerId = $user->isStaff() ? ($request->integer('customer_id') ?: null) : null;

        $payments = Payment::query()->visibleTo($user)->with(['customer', 'project', 'bill', 'creator'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($customerId, fn ($q, $id) => $q->where('customer_id', $id))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('paid_on')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'status' => $status,
            'customerId' => $customerId,
            'customers' => $user->isStaff() ? TransactionController::customerOptions($user) : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Payment::class);
        $user = $request->user();
        $bill = $request->integer('bill') ? Bill::visibleTo($user)->where('number', $request->integer('bill'))->first() : null;
        $customerId = $user->isCustomer() ? $user->id : ($bill?->customer_id ?? ($request->integer('customer_id') ?: null));
        $customer = $customerId ? User::find($customerId) : null;

        // By default the customer pays the whole debt at this moment, not only the last bill.
        $debt = $customer ? Bills::debtOf($customer) : 0;

        return view('payments.form', [
            'payment' => new Payment([
                'customer_id' => $customerId,
                'project_id' => $bill?->project_id,
                'bill_id' => $bill?->id,
                'amount' => $user->isCustomer() && $debt > 0 ? $debt : null,
                'paid_on' => now(),
                'status' => $user->isCustomer() ? Payment::PENDING : Payment::ACCEPTED,
            ]),
            'debt' => $debt,
            'bill' => $bill,
        ] + $this->formOptions($user, $bill));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Payment::class);
        $user = $request->user();

        $payment = Payment::create($this->validated($request, $user) + ['created_by' => $user->id, 'updated_by' => $user->id]);

        if ($user->isCustomer()) {
            return redirect()->route('payments.index')->with('success', __('transactions.voucher_saved'));
        }

        return $this->redirectBack($request, route('transactions.index'))->with('success', __('transactions.payment_saved'));
    }

    public function edit(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);
        $user = $request->user();

        return view('payments.form', [
            'payment' => $payment,
            'debt' => Bills::debtOf($payment->customer),
            'bill' => $payment->bill,
        ] + $this->formOptions($user, $payment->bill));
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);
        $user = $request->user();

        $payment->update($this->validated($request, $user, $payment) + ['updated_by' => $user->id]);

        return $this->redirectBack($request, $this->listPage($user))->with('success', __('app.saved'));
    }

    public function destroy(Request $request, Payment $payment)
    {
        $this->authorize('delete', $payment);
        $payment->delete();

        return $this->redirectBack($request, $this->listPage($request->user()))->with('success', __('app.deleted'));
    }

    /** Where a saved form goes when the user did not come from a list: developers → transactions, customers → their vouchers. */
    private function listPage(User $user): string
    {
        return $user->isCustomer() ? route('payments.index') : route('transactions.index');
    }

    /** Accept or decline a voucher (developers). */
    public function review(Request $request, Payment $payment)
    {
        $this->authorize('review', $payment);
        $status = $request->validate(['status' => ['required', Rule::in([Payment::ACCEPTED, Payment::DECLINED])]])['status'];
        $payment->update(['status' => $status, 'updated_by' => $request->user()->id]);

        return back()->with('success', __('transactions.voucher_'.$status));
    }

    private function formOptions(User $user, ?Bill $bill): array
    {
        if ($user->isCustomer()) {
            $projects = $user->projects()->orderBy('name')->get();
            $bills = Bill::visibleTo($user)->with('project')->orderByDesc('issued_on')->orderByDesc('id')->get();
            // Bank details to pay to: the developer who issued the bill, else the developers of the customer's projects.
            $banks = $bill?->creator?->hasBankInfo()
                ? collect([$bill->creator])
                : User::whereIn('id', $projects->flatMap(fn ($p) => $p->developers()->pluck('users.id')))
                    ->where(fn ($q) => $q->whereNotNull('card_number')->orWhereNotNull('iban'))->get();

            return ['customers' => collect(), 'projects' => $projects, 'bills' => $bills, 'banks' => $banks];
        }

        return [
            'customers' => TransactionController::customerOptions($user),
            'projects' => Project::query()->visibleTo($user)->orderBy('name')->get(),
            'bills' => Bill::visibleTo($user)->with('project')->orderByDesc('issued_on')->orderByDesc('id')->get(),
            'banks' => collect(),
        ];
    }

    private function validated(Request $request, User $user, ?Payment $payment = null): array
    {
        $request->merge([
            'amount' => Money::parse($request->input('amount')),
            'paid_time' => $request->filled('paid_time') ? Dates::latinDigits(trim($request->input('paid_time'))) : null,
            'reference_no' => $request->filled('reference_no') ? Dates::latinDigits(trim($request->input('reference_no'))) : null,
        ]);

        if ($user->isCustomer()) {
            $request->merge(['customer_id' => $user->id]);
            $customerIds = [$user->id];
        } else {
            $customerIds = User::query()->customersOf($user)->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        }
        $customer = in_array((int) $request->input('customer_id'), $customerIds, true)
            ? User::find((int) $request->input('customer_id'))
            : null;
        // The project is optional, but must be a project of that customer that the user can see.
        $projectIds = $customer
            ? array_values(array_filter(
                $customer->projects()->pluck('projects.id')->map(fn ($id) => (int) $id)->all(),
                fn ($id) => $user->canAccessProject($id),
            ))
            : [];
        // The bill is optional too: one of that customer's bills.
        $billIds = $customer ? Bill::visibleTo($user)->where('customer_id', $customer->id)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        $customerForm = $user->isCustomer();

        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::in($customerIds)],
            'project_id' => ['nullable', 'integer', Rule::in($projectIds)],
            'bill_id' => ['nullable', 'integer', Rule::in($billIds)],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'paid_on' => ['required', 'string', fn ($attr, $value, $fail) => Dates::isValid($value)
                ? null : $fail(__('validation.date', ['attribute' => __('transactions.fields.date')]))],
            'paid_time' => [$customerForm ? 'required' : 'nullable', 'string', 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/'],
            'reference_no' => [$customerForm ? 'required' : 'nullable', 'string', 'max:60'],
            'status' => [$customerForm ? 'prohibited' : 'nullable', Rule::in(Payment::STATUSES)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], [
            'customer_id' => __('transactions.fields.customer'),
            'project_id' => __('transactions.fields.project'),
            'bill_id' => __('transactions.fields.bill'),
            'amount' => __('transactions.fields.amount'),
            'paid_on' => __('transactions.fields.date'),
            'paid_time' => __('transactions.fields.time'),
            'reference_no' => __('transactions.fields.reference_no'),
            'status' => __('transactions.fields.status'),
            'description' => __('transactions.fields.description'),
        ]);

        $data['paid_on'] = Dates::parse($data['paid_on'])->toDateString();
        $data['paid_time'] = isset($data['paid_time']) ? sprintf('%05s', $data['paid_time']) : null;
        $data['project_id'] ??= null;
        $data['bill_id'] ??= null;
        if ($data['bill_id']) {
            $data['project_id'] = Bill::find($data['bill_id'])->project_id; // a bill payment belongs to the bill's project
        }
        if ($customerForm) {
            $data['status'] = Payment::PENDING; // a customer's voucher waits for the developer
        } elseif (empty($data['status'])) {
            $data['status'] = $payment->status ?? Payment::ACCEPTED; // a developer's payment is accepted at once
        }

        return $data;
    }
}
