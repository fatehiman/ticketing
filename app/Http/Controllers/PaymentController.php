<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Staff add, edit and delete customer payments. The list is the transactions page. */
class PaymentController extends Controller
{
    public function create(Request $request)
    {
        $this->authorize('create', Payment::class);

        return view('payments.form', [
            'payment' => new Payment([
                'customer_id' => $request->integer('customer_id') ?: null,
                'paid_on' => now(),
            ]),
            'customers' => TransactionController::customerOptions($request->user()),
            'projects' => Project::query()->visibleTo($request->user())->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Payment::class);
        $user = $request->user();

        Payment::create($this->validated($request, $user) + ['created_by' => $user->id, 'updated_by' => $user->id]);

        return redirect()->route('transactions.index')->with('success', __('transactions.payment_saved'));
    }

    public function edit(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        return view('payments.form', [
            'payment' => $payment,
            'customers' => TransactionController::customerOptions($request->user()),
            'projects' => Project::query()->visibleTo($request->user())->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);
        $user = $request->user();

        $payment->update($this->validated($request, $user) + ['updated_by' => $user->id]);

        return redirect()->route('transactions.index')->with('success', __('app.saved'));
    }

    public function destroy(Payment $payment)
    {
        $this->authorize('delete', $payment);
        $payment->delete();

        return redirect()->route('transactions.index')->with('success', __('app.deleted'));
    }

    private function validated(Request $request, User $user): array
    {
        $request->merge(['amount' => Money::parse($request->input('amount'))]);

        $customerIds = User::query()->customersOf($user)->pluck('users.id')->map(fn ($id) => (int) $id)->all();
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

        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::in($customerIds)],
            'project_id' => ['nullable', 'integer', Rule::in($projectIds)],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'paid_on' => ['required', 'string', fn ($attr, $value, $fail) => Dates::isValid($value)
                ? null : $fail(__('validation.date', ['attribute' => __('transactions.fields.date')]))],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], [
            'customer_id' => __('transactions.fields.customer'),
            'project_id' => __('transactions.fields.project'),
            'amount' => __('transactions.fields.amount'),
            'paid_on' => __('transactions.fields.date'),
            'description' => __('transactions.fields.description'),
        ]);

        $data['paid_on'] = Dates::parse($data['paid_on'])->toDateString();
        $data['project_id'] ??= null;

        return $data;
    }
}
