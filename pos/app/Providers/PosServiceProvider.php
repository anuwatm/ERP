<?php

namespace Pos\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('pos-login', function (Request $request): array {
            $limits = [Limit::perMinute(5)->by('ip:'.$request->ip())];
            $email = $request->input('email');
            if (is_string($email) && strlen($email) <= 255) {
                $limits[] = Limit::perMinute(10)->by('account:'.hash('sha256', mb_strtolower(trim($email))));
            }

            return $limits;
        });
        RateLimiter::for('pos-public', fn ($r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('pos-connector', fn ($r) => Limit::perMinute(120)->by($r->server('SSL_CLIENT_FINGERPRINT', $r->ip())));
    }
}
