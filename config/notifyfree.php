<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NotifyFree 基础配置
    |--------------------------------------------------------------------------
    |
    | 基础配置需要在 .env 文件中设置以下参数：
    | - NOTIFYFREE_TOKEN: 您的 NotifyFree API 令牌
    | - NOTIFYFREE_APP_ID: 您的应用程序 ID
    |
    | 其他配置项都有合理的默认值，无需额外配置即可使用。
    |
    */

    // 必需配置项
    'token' => env('NOTIFYFREE_TOKEN'),
    'app_id' => env('NOTIFYFREE_APP_ID'),

    // 服务端点 - 生产环境使用默认值即可
    'endpoint' => env('NOTIFYFREE_ENDPOINT', 'https://api.notifyfree.com/v1/messages'),

    /*
    |--------------------------------------------------------------------------
    | NotifyFree 高级配置
    |--------------------------------------------------------------------------
    |
    | 以下为高级配置选项，一般用户无需修改，适用于有特殊需求的高级用户。
    | 如需自定义，可在 .env 文件中设置对应的环境变量。
    |
    */

    /*
    | 连接设置
    */
    'timeout' => (int) env('NOTIFYFREE_TIMEOUT', 30),
    'retry_attempts' => (int) env('NOTIFYFREE_RETRY', 3),

    /*
    | 批处理配置
    */
    'batch' => [
        'enabled' => env('NOTIFYFREE_BATCH_ENABLED', true),
        'buffer_size' => (int) env('NOTIFYFREE_BATCH_BUFFER_SIZE', 50),
        'flush_timeout' => (int) env('NOTIFYFREE_BATCH_FLUSH_TIMEOUT', 5), // seconds
    ],

    /*
    | 缓存配置
    */
    'cache' => [
        'service_status_enabled' => env('NOTIFYFREE_CACHE_SERVICE_STATUS', true),
        'service_status_ttl' => (int) env('NOTIFYFREE_CACHE_SERVICE_STATUS_TTL', 60), // seconds
    ],

    /*
    | 向后兼容配置（已废弃）
    */
    'batch_size' => (int) env('NOTIFYFREE_BATCH_SIZE', 10), // deprecated: use batch.buffer_size
    'handler' => env('NOTIFYFREE_HANDLER', \NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler::class), // deprecated

    /*
    | 日志格式配置
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
