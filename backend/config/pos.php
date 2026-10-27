<?php

return [
    'local_admin_sync_enabled' => env('POS_LOCAL_ADMIN_SYNC_ENABLED', false),
    'local_tax_documents_enabled' => env('POS_LOCAL_TAX_DOCUMENTS_ENABLED', false),
    'local_checkpoints_enabled' => env('POS_LOCAL_CHECKPOINTS_ENABLED', false),
    'local_recalls_enabled' => env('POS_LOCAL_RECALLS_ENABLED', false),
    'local_refunds_enabled' => env('POS_LOCAL_REFUNDS_ENABLED', false),
    'local_cash_movements_enabled' => env('POS_LOCAL_CASH_MOVEMENTS_ENABLED', false),
    'local_discounts_enabled' => env('POS_LOCAL_DISCOUNTS_ENABLED', false),
    'local_restaurant_enabled' => env('POS_LOCAL_RESTAURANT_ENABLED', false),
    'local_return_inspections_enabled' => env('POS_LOCAL_RETURN_INSPECTIONS_ENABLED', false),
    'local_transport_enabled' => false,
    'local_connections' => [],
    'local_quota_recovery_enabled' => false,
    'max_event_bytes' => 262144,
    'max_batch_events' => 100,
    'max_attempts' => 5,
];
