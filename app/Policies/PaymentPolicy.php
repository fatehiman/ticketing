<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payments (bank vouchers).
 * - Developers add them (accepted at once), and any developer of the customer can edit, accept,
 *   decline or delete them, as long as the payment has no project or is on one of their projects.
 * - Customers register their own payments (pending) and can edit or delete them while pending.
 * - Admins only read payments (they supervise; customers belong to the developers).
 */
class PaymentPolicy
{
    public function create(User $user): bool
    {
        return $user->isDeveloper() || $user->isCustomer();
    }

    public function update(User $user, Payment $payment): bool
    {
        if ($user->isCustomer()) {
            return $payment->customer_id === $user->id && $payment->isPending();
        }

        return $this->review($user, $payment);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->update($user, $payment);
    }

    /** Accept or decline a voucher. */
    public function review(User $user, Payment $payment): bool
    {
        return $user->isDeveloper()
            && ($payment->project_id === null || $user->canAccessProject($payment->project_id))
            && User::query()->customersOf($user)->whereKey($payment->customer_id)->exists();
    }
}
