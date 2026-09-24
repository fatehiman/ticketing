<?php

namespace App\Http\Controllers;

use App\Models\ApiAuthRequest;
use App\Models\ApiToken;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Step 2 of the bot login (web side): the developer opens the link, signs in (if needed)
 * and picks the project the bot's token is linked to. No token is ever shown here.
 */
class ApiAuthController extends Controller
{
    public function show(Request $request, string $publicId)
    {
        $auth = ApiAuthRequest::where('public_id', $publicId)->first();
        if ($error = $this->problem($auth)) {
            return $this->message($error, false);
        }

        // The first visit counts as "opened", also before sign-in.
        $auth->markOpened();

        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('login'));
        }
        if (! $user->isStaff()) {
            return $this->message('api.only_staff', false);
        }

        $projects = $this->projects($request);
        if ($projects->isEmpty()) {
            return $this->message('api.no_projects', false);
        }
        // Only one project: nothing to choose.
        if ($projects->count() === 1) {
            return $this->approve($auth, $user, $projects->first()->id);
        }

        return view('api-auth.choose', ['auth' => $auth, 'projects' => $projects]);
    }

    public function store(Request $request, string $publicId)
    {
        $auth = ApiAuthRequest::where('public_id', $publicId)->first();
        if ($error = $this->problem($auth)) {
            return $this->message($error, false);
        }
        $user = $request->user();
        if (! $user->isStaff()) {
            return $this->message('api.only_staff', false);
        }

        $data = $request->validate([
            'project' => ['required', Rule::in(array_merge(['none'], $this->projects($request)->pluck('id')->map(fn ($id) => (string) $id)->all()))],
        ]);

        return $this->approve($auth, $user, $data['project'] === 'none' ? null : (int) $data['project']);
    }

    private function approve(ApiAuthRequest $auth, $user, ?int $projectId)
    {
        $auth->forceFill(['user_id' => $user->id, 'project_id' => $projectId, 'approved_at' => now()])->save();

        return $this->message('api.approved', true, [
            'project' => $projectId ? Project::find($projectId)?->name : __('api.no_fixed_project'),
            'days' => ApiToken::LIFETIME_DAYS,
        ]);
    }

    /** Translation key of the problem, or null when the request can still be approved. */
    private function problem(?ApiAuthRequest $auth): ?string
    {
        return match (true) {
            $auth === null => 'api.link_invalid',
            $auth->isClaimed() || $auth->isApproved() => 'api.link_used',
            $auth->isExpiredForApproval() => 'api.link_expired',
            default => null,
        };
    }

    private function projects(Request $request)
    {
        return Project::query()->visibleTo($request->user())->active()->orderBy('name')->get();
    }

    private function message(string $key, bool $success, array $replace = [])
    {
        return response()->view('api-auth.message', [
            'success' => $success,
            'text' => __($key, $replace),
        ], $success ? 200 : 403);
    }
}
