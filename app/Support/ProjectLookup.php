<?php

namespace App\Support;

use App\Exceptions\ApiError;
use App\Models\Project;
use App\Models\User;

/** Full access API: find a project the user can see by id, code or exact name. */
class ProjectLookup
{
    public static function find(User $user, ?string $value): Project
    {
        $value = trim(Dates::latinDigits((string) $value));
        if ($value === '') {
            throw new ApiError('project_required', 'Send "project" (id, code or name). GET /api/full/projects lists your projects.');
        }

        $base = fn () => Project::query()->visibleTo($user);
        $project = (ctype_digit($value) ? $base()->where('id', (int) $value)->first() : null)
            ?? $base()->where('code', $value)->first();

        if (! $project) {
            $byName = $base()->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->get();
            if ($byName->count() > 1) {
                throw new ApiError('project_ambiguous', "More than one project is named \"{$value}\". Use the project id or code.");
            }
            $project = $byName->first();
        }

        return $project ?? throw new ApiError('project_not_found', "Project \"{$value}\" was not found or you have no access. GET /api/full/projects lists your projects.", 404);
    }
}
