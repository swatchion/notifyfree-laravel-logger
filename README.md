# NotifyFree Laravel Log Channel

一个用于 Laravel 的 NotifyFree 日志通道扩展包，通过 Laravel stack 通道设计实现日志的远程发送和本地备份。

## 功能特性

- 🔄 **Stack 通道集成**: 通过 Laravel stack 通道实现日志双写
- 🔐 **Token 认证**: 通过 API Token 进行安全认证
- 📦 **多种处理器**: 支持同步、批量、监控增强等处理方式
- 🛡️ **自动 Fallback**: 发送失败时自动记录到本地日志
- 🎛️ **可配置**: 丰富的配置选项满足不同需求
- 🔒 **敏感数据过滤**: 自动过滤密码、Token 等敏感信息
- 📊 **服务监控**: 支持 NotifyFree 服务状态监控

## 系统要求

- PHP 8.2+
- Laravel 11.0+
- Monolog 3.0+
- Guzzle HTTP 7.0+

## 安装和配置

### 1. 环境变量配置

在 `.env` 文件中添加 NotifyFree 配置：

```env
# NotifyFree 服务配置
NOTIFYFREE_ENDPOINT=http://127.0.0.1:8000/api/v1/messages
NOTIFYFREE_TOKEN=your_token_here
NOTIFYFREE_APP_ID=your_app_id_here

# 可选配置
NOTIFYFREE_TIMEOUT=30
NOTIFYFREE_RETRY=3
NOTIFYFREE_BATCH_SIZE=10

# 格式配置
NOTIFYFREE_INCLUDE_CONTEXT=true
NOTIFYFREE_INCLUDE_EXTRA=true
NOTIFYFREE_TIMESTAMP_FORMAT="Y-m-d H:i:s"
NOTIFYFREE_MAX_MESSAGE_LENGTH=1000
```

### 2. 配置日志通道

在 `config/logging.php` 中配置 stack 通道实现自动 fallback：

```php
'channels' => [
    // Stack 通道配置 - 实现自动 fallback
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'notifyfree'], // 同时写入本地和远程
        'ignore_exceptions' => false,
    ],
    
    // NotifyFree 通道配置
    'notifyfree' => [
        'driver' => 'notifyfree',
        'level' => env('LOG_LEVEL', 'error'),
    ],
    
    // 单个文件通道作为 fallback
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

### 3. 处理器选择

可以选择不同的处理器：

```env
# 基础同步处理器（默认）
NOTIFYFREE_HANDLER=NotifyFree\LaravelLogChannel\Handlers\NotifyFreeHandler

# 批量处理器 - 提高性能
NOTIFYFREE_HANDLER=NotifyFree\LaravelLogChannel\Handlers\BatchNotifyFreeHandler

# 监控增强处理器 - 带服务状态监控
NOTIFYFREE_HANDLER=NotifyFree\LaravelLogChannel\Handlers\CachedNotifyFreeHandler
```

## 使用方法

### 基本用法

```php
use Illuminate\Support\Facades\Log;

// 使用默认 stack 通道（推荐）
Log::info('用户登录成功', ['user_id' => 123]);
Log::error('数据库连接失败', ['error' => $exception->getMessage()]);

// 直接使用 notifyfree 通道
Log::channel('notifyfree')->warning('API 调用异常');
```

### 测试和监控

```bash
# 测试 NotifyFree 连接
php artisan notifyfree:test --level=error

# 检查本地日志 fallback
tail -f storage/logs/laravel.log
```

### 服务状态监控

使用 CachedNotifyFreeHandler 时可以监控服务状态：

```php
$handler = new \NotifyFree\LaravelLogChannel\Handlers\CachedNotifyFreeHandler($config);

// 测试连接
$isAvailable = $handler->testConnection();

// 获取服务状态
$status = $handler->getServiceStatus();

// 记录服务状态到日志
$handler->logServiceStatus();
```

## 工作原理

### Stack 通道设计

1. **正常情况**: 日志同时写入 `single` 通道（本地文件）和 `notifyfree` 通道（远程服务）
2. **发送失败**: NotifyFree 发送失败时，错误信息通过 `single` 通道记录到本地日志
3. **完全 Fallback**: 原始日志始终保存在本地文件中，确保不丢失

### 处理器特性

- **NotifyFreeHandler**: 基础同步发送，简单可靠
- **BatchNotifyFreeHandler**: 批量处理，减少网络请求，提高性能
- **CachedNotifyFreeHandler**: 增强服务监控，便于状态跟踪

## 配置详解

完整的配置选项请参考 `config/notifyfree.php`：

```php
return [
    'endpoint' => env('NOTIFYFREE_ENDPOINT'),
    'token' => env('NOTIFYFREE_TOKEN'),
    'app_id' => env('NOTIFYFREE_APP_ID'),
    
    'timeout' => (int) env('NOTIFYFREE_TIMEOUT', 30),
    'retry_attempts' => (int) env('NOTIFYFREE_RETRY', 3),
    'batch_size' => (int) env('NOTIFYFREE_BATCH_SIZE', 10),
    
    'format' => [
        'include_context' => env('NOTIFYFREE_INCLUDE_CONTEXT', true),
        'sensitive_keys' => ['password', 'token', 'secret'],
    ],
];
```

## 许可证

MIT License
