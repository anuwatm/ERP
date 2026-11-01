<?php

return [
    'heartbeats_enabled' => env('OPS_HEARTBEATS_ENABLED', false),
    'scheduler_max_age' => 180,
    'worker_max_age' => 300,
    'queues' => array_values(array_filter(array_map('trim', explode(',', env('OPS_MONITORED_QUEUES', 'default,etax'))))),
];
