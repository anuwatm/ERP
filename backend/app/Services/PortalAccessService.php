<?php

namespace App\Services;

use App\Models\PortalAccessToken;
use App\Models\PortalSession;
use App\Models\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortalAccessService
{
    public function issueMagicLink(PortalUser $user): string
    {
        $raw = Str::random(80);
        PortalAccessToken::create(['portal_user_id' => $user->id, 'token_hash' => hash('sha256', $raw), 'expires_at' => now()->addMinutes(30)]);

        return $raw;
    }

    public function consumeMagicLink(string $raw, Request $request): ?string
    {
        return DB::transaction(function () use ($raw, $request) {
            $token = PortalAccessToken::where('token_hash', hash('sha256', $raw))->lockForUpdate()->first();
            if (! $token || $token->used_at || $token->expires_at->isPast()) {
                return null;
            }
            $user = PortalUser::whereKey($token->portal_user_id)->where('is_active', true)->lockForUpdate()->first();
            if (! $user) {
                return null;
            }
            $token->update(['used_at' => now()]);
            $rawSession = Str::random(80);
            PortalSession::create(['portal_user_id' => $user->id, 'token_hash' => hash('sha256', $rawSession), 'user_agent_hash' => hash('sha256', (string) $request->userAgent()), 'ip_hash' => hash('sha256', (string) $request->ip()), 'expires_at' => now()->addHours(8), 'last_seen_at' => now()]);
            $user->update(['last_login_at' => now()]);

            return $rawSession;
        });
    }

    public function user(Request $request): ?PortalUser
    {
        $raw = $request->cookie('erp_portal_session');
        if (! $raw) {
            return null;
        }
        $session = PortalSession::where('token_hash', hash('sha256', $raw))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if (! $session) {
            return null;
        }
        $session->update(['last_seen_at' => now()]);

        return PortalUser::whereKey($session->portal_user_id)->where('is_active', true)->first();
    }

    public function revoke(Request $request): void
    {
        $raw = $request->cookie('erp_portal_session');
        if (! $raw) {
            return;
        }

        PortalSession::where('token_hash', hash('sha256', $raw))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
