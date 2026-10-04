<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;

/** Developers issue and delete bills of their projects; customers read their own; admins read all. */
class BillPolicy
{
    public function create(User $user): bool
    {
        return $user->isDeveloper();
    }

    public function view(User $user, Bill $bill): bool
    {
        if ($user->isCustomer()) {
            return $bill->customer_id === $user->id;
        }

        return $user->canAccessProject($bill->project_id);
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $user->isDeveloper() && $user->canAccessProject($bill->project_id);
    }
}
