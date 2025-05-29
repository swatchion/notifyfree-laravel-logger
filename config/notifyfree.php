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

    'token' => env('NOTIFYFREE_TOKEN'),

    'app_id' => env('NOTIFYFREE_APP_ID'),

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

    'batch_size' => (int) env('NOTIFYFREE_BATCH_SIZE', 10),

    /*
    |--------------------------------------------------------------------------
    | Handler Configuration
    |--------------------------------------------------------------------------
    |
    | Choose the appropriate handler for your needs:
    | - NotifyFreeHandler: Basic synchronous sending
    | - BatchNotifyFreeHandler: Batched sending for better performance
    | - CachedNotifyFreeHandler: With local fallback support
    |
    */
    'handler' => env('NOTIFYFREE_HANDLER', \NotifyFree\LaravelLogChannel\Handlers\NotifyFreeHandler::class),

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

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | When NotifyFree service is unavailable, logs can be stored locally
    | and optionally retried later.
    |
    */
    'fallback' => [
        'enabled' => env('NOTIFYFREE_FALLBACK_ENABLED', true),
        'local_storage_path' => env(
            'NOTIFYFREE_FALLBACK_PATH',
            storage_path('logs/notifyfree-fallback.log')
        ),
        'max_file_size' => env('NOTIFYFREE_FALLBACK_MAX_SIZE', 10 * 1024 * 1024), // 10MB
    ],
];
