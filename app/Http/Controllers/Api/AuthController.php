<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiAuthRequest;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Bot login in 3 steps. See API.md. */
class AuthController extends Controller
{
    /** Step 1: create a login request. */
    public function start(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'require_project' => ['nullable', 'boolean'],
        ]);
        [$auth, $secret] = ApiAuthRequest::start($data['name'] ?? null, (bool) ($data['require_project'] ?? false));

        return response()->json([
            'ok' => true,
            'request_id' => $auth->public_id,
            'secret' => $secret,
            'login_url' => route('api-auth.show', $auth->public_id),
            'open_within_seconds' => ApiAuthRequest::OPEN_SECONDS,
            'next_step' => 'Send login_url to the developer (never send the secret). The developer must open it within '
                .ApiAuthRequest::OPEN_SECONDS.' seconds and sign in. When the developer says it is done, call POST /api/auth/token with request_id and secret.',
        ]);
    }

    /** Step 3: trade request id + secret for the token (once). */
    public function token(Request $request)
    {
        $data = $request->validate([
            'request_id' => ['required', 'string', 'max:40'],
            'secret' => ['required', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($data) {
            $auth = ApiAuthRequest::where('public_id', $data['request_id'])->lockForUpdate()->first();

            if (! $auth || ! $auth->checkSecret($data['secret'])) {
                return $this->fail('not_found', 'Unknown request_id or wrong secret. Start again with POST /api/auth/start.', 404);
            }
            if ($auth->isClaimed()) {
                return $this->fail('already_used', 'The token of this request was already given. Start again with POST /api/auth/start.', 410);
            }
            if ($auth->isExpired()) {
                return $this->fail('expired', 'This login request has expired. Start again with POST /api/auth/start.', 410);
            }
            if (! $auth->isApproved()) {
                return response()->json([
                    'ok' => false,
                    'status' => 'pending',
                    'error' => 'pending',
                    'message' => 'The developer has not finished the login yet. Ask the developer to open the link and sign in, then call this again.',
                ], 202);
            }

            $user = $auth->user;
            if (! $user || ! $user->is_active || ! $user->isDeveloper()) {
                return $this->fail('forbidden', 'This user cannot use the API.', 403);
            }

            [$token, $plain] = ApiToken::issue($user, $auth->project_id, $auth->name);
            $auth->forceFill(['claimed_at' => now(), 'api_token_id' => $token->id])->save();
            $project = $token->project;

            return response()->json([
                'ok' => true,
                'status' => 'approved',
                'token' => $plain,
                'token_type' => 'Bearer',
                'expires_at' => $token->expires_at->toIso8601String(),
                'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value],
                'project' => $project ? ['id' => $project->id, 'code' => $project->code, 'name' => $project->name] : null,
                'next_step' => 'Keep the token. Send it in every call as the header "Authorization: Bearer <token>".'
                    .($project ? '' : ' This token has no fixed project: send "project" in every call.'),
            ]);
        });
    }

    private function fail(string $error, string $message, int $status)
    {
        return response()->json(['ok' => false, 'status' => $error, 'error' => $error, 'message' => $message], $status);
    }
}
