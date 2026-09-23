<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payments are added by staff. Any developer of the customer (and admins) can edit or delete them,
 * as long as the payment has no project or is on one of the developer's projects.
 */
class PaymentPolicy
{
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Payment $payment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isDeveloper()
            && ($payment->project_id === null || $user->canAccessProject($payment->project_id))
            && User::query()->customersOf($user)->whereKey($payment->customer_id)->exists();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->update($user, $payment);
    }
}
