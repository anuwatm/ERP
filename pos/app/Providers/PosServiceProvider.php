<?php

namespace Pos\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class PosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('pos-login', fn ($r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('pos-public', fn ($r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('pos-connector', fn ($r) => Limit::perMinute(120)->by($r->server('SSL_CLIENT_FINGERPRINT', $r->ip())));
    }
}
