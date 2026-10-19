<?php

return [
    'webhooks_enabled' => env('EXTERNAL_API_WEBHOOKS_ENABLED', false),
    'key_lifetime_days' => 90,
    'max_lifetime_days' => 365,
    'max_active_keys' => 20,
    'requests_per_minute' => 60,
    'invalid_requests_per_minute' => 30,
    'max_date_days' => 366,
];
