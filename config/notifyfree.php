<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NotifyFree API Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how the log channel connects to NotifyFree service.
    | Make sure to set the correct endpoint and authentication credentials.
    |
    */
    'endpoint' => env('NOTIFYFREE_ENDPOINT', 'https://api.notifyfree.com/v1/logs'),

    'token' => env('NOTIFYFREE_TOKEN','ABCDEFGHIJKLMNOPQRSTUVWXYZ'),

    'app_id' => env('NOTIFYFREE_APP_ID','1234567890'),

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure timeout, retry behavior and batch processing settings.
    |
    */
    'timeout' => (int) env('NOTIFYFREE_TIMEOUT', 30),

    'retry_attempts' => (int) env('NOTIFYFREE_RETRY', 3),

    /*
    |--------------------------------------------------------------------------
    | Batch Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure batch processing behavior for improved performance.
    | Batch processing is enabled by default and uses a fixed-length buffer.
    |
    */
    'batch' => [
        'enabled' => env('NOTIFYFREE_BATCH_ENABLED', true),
        'buffer_size' => (int) env('NOTIFYFREE_BATCH_BUFFER_SIZE', 50),
        'flush_timeout' => (int) env('NOTIFYFREE_BATCH_FLUSH_TIMEOUT', 5), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for service status and connection monitoring.
    |
    */
    'cache' => [
        'service_status_enabled' => env('NOTIFYFREE_CACHE_SERVICE_STATUS', true),
        'service_status_ttl' => (int) env('NOTIFYFREE_CACHE_SERVICE_STATUS_TTL', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Configuration (Deprecated)
    |--------------------------------------------------------------------------
    |
    | These settings are kept for backward compatibility but are deprecated.
    | Use the new 'batch' configuration section instead.
    |
    */
    'batch_size' => (int) env('NOTIFYFREE_BATCH_SIZE', 10), // deprecated: use batch.buffer_size

    /*
    |--------------------------------------------------------------------------
    | Handler Configuration (Deprecated)
    |--------------------------------------------------------------------------
    |
    | The handler configuration is deprecated. All functionality is now
    | integrated into the main NotifyFreeHandler with configurable features.
    |
    */
    'handler' => env('NOTIFYFREE_HANDLER', \NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler::class), // deprecated

    /*
    |--------------------------------------------------------------------------
    | Log Format Configuration
    |--------------------------------------------------------------------------
    |
    | Control how log messages are formatted before sending to NotifyFree.
    |
    */
    'format' => [
        'include_context' => env('NOTIFYFREE_INCLUDE_CONTEXT', true),
        'include_extra' => env('NOTIFYFREE_INCLUDE_EXTRA', true),
        'timestamp_format' => env('NOTIFYFREE_TIMESTAMP_FORMAT', 'Y-m-d H:i:s'),
        'max_message_length' => (int) env('NOTIFYFREE_MAX_MESSAGE_LENGTH', 1000),

        // 敏感数据过滤
        'sensitive_keys' => [
            'password', 'token', 'secret', 'key', 'auth',
            'api_key', 'access_token', 'refresh_token', 'authorization'
        ],
    ],

];
