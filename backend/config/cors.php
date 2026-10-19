<?php

return [
    // Preserve existing callback/CSRF behavior. Integration summaries are server-to-server.
    'paths' => ['api/gateway/*', 'api/e-tax/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
