<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->canAccessProject($project);
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /** Admins manage every project, developers only projects they are a member of. */
    public function update(User $user, Project $project): bool
    {
        return $user->isAdmin() || ($user->isDeveloper() && $user->canAccessProject($project));
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /** Sprints are the developer's work planning: admins only read them. */
    public function manageSprints(User $user, Project $project): bool
    {
        return $user->isDeveloper() && $user->canAccessProject($project);
    }

    /** Only admins assign developers to projects. */
    public function manageDevelopers(User $user): bool
    {
        return $user->isAdmin();
    }
}
