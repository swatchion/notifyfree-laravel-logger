# NotifyFree Laravel Log Channel

一个用于 Laravel 的 NotifyFree 日志通道扩展包，通过 Laravel 原生 Stack 通道设计实现可靠的日志远程发送。

## 核心设计理念

本包采用 **Laravel Stack 通道** 设计，利用框架原生的多通道机制实现日志的可靠传输，避免重复造轮子，提供更简单、可靠的解决方案。

### 架构优势

- ✅ **框架级别的可靠性**：利用 Laravel Stack 驱动确保多通道并行工作
- ✅ **性能优化**：并行写入，不阻塞应用响应
- ✅ **配置简单**：无需复杂的 fallback 逻辑
- ✅ **维护成本低**：减少自定义代码，提高稳定性

## 功能特性

- 🔄 **Stack 通道集成**: 通过 Laravel Stack 通道实现日志双写（本地 + 远程）
- 🚀 **并行处理**: 本地日志和远程发送同时进行，不相互阻塞
- 🔐 **Token 认证**: 通过 API Token 进行安全认证
- 🛡️ **框架级 Fallback**: 利用 Laravel Stack 驱动的原生可靠性
- 🎛️ **可配置**: 丰富的配置选项满足不同需求
- 🔒 **敏感数据过滤**: 自动过滤密码、Token 等敏感信息
- 🔄 **重试机制**: 内置指数退避重试机制
- 📊 **连接测试**: 提供服务连接状态测试功能
- ⚡ **智能批处理**: 默认启用的固定长度缓冲区批量发送，支持定时 flush
- 🚀 **并发处理**: Guzzle Promise + curl_multi 并发发送，真正的 I/O 并发，无阻塞
- 🧠 **智能缓存**: 服务状态缓存，减少不必要的连接检查

## 系统要求

- PHP 7.4+ (推荐 PHP 8.0+)
- Laravel 8.0+ (支持 Laravel 8.x, 9.x, 10.x, 11.x, 12.x)
- Monolog 2.0+ 或 3.0+
- Guzzle HTTP 6.5+ 或 7.0+

### 并发处理特性

- **高性能并发**: 使用 Guzzle Promise + curl_multi 实现真正的 I/O 并发
- **广泛兼容**: 支持 PHP 7.4+ 的所有版本，无需特殊扩展
- **自动降级**: 并发处理失败时自动回退到串行处理模式

## 快速开始

### 1. 环境变量配置

```env
LOG_CHANNEL=stack
NOTIFYFREE_ENDPOINT=http://127.0.0.1:8000/api/v1/messages
NOTIFYFREE_TOKEN=your_token_here
NOTIFYFREE_APP_ID=your_app_id_here
```

### 2. 日志通道配置

在 `config/logging.php` 中配置：

```php
'stack' => [
    'driver' => 'stack',
    'channels' => ['single', 'notifyfree'], // 本地 + 远程并行
],
'notifyfree' => [
    'driver' => 'notifyfree',
    'endpoint' => env('NOTIFYFREE_ENDPOINT'),
    'token' => env('NOTIFYFREE_TOKEN'),
    'app_id' => env('NOTIFYFREE_APP_ID'),
    'level' => env('LOG_LEVEL', 'error'),
],
```

### 3. 开始使用

```php
use Illuminate\Support\Facades\Log;

// 使用默认 stack 通道（推荐）- 自动双写到本地和远程
Log::info('用户登录', ['user_id' => 123]);
Log::error('系统错误', ['error' => '数据库连接失败']);
```

## 详细配置

### 完整环境变量

```env
# 必需配置
LOG_CHANNEL=stack
NOTIFYFREE_ENDPOINT=http://127.0.0.1:8000/api/v1/messages
NOTIFYFREE_TOKEN=your_token_here
NOTIFYFREE_APP_ID=your_app_id_here

# 可选配置
NOTIFYFREE_TIMEOUT=30
NOTIFYFREE_RETRY=3

# 批处理配置（始终启用）
NOTIFYFREE_BATCH_BUFFER_SIZE=50
NOTIFYFREE_BATCH_FLUSH_TIMEOUT=5

# 缓存配置
NOTIFYFREE_CACHE_SERVICE_STATUS=true
NOTIFYFREE_CACHE_SERVICE_STATUS_TTL=60

# 格式化配置
NOTIFYFREE_INCLUDE_CONTEXT=true
NOTIFYFREE_INCLUDE_EXTRA=true
NOTIFYFREE_TIMESTAMP_FORMAT="Y-m-d H:i:s"
NOTIFYFREE_MAX_MESSAGE_LENGTH=1000
LOG_LEVEL=debug

# 废弃配置（向后兼容）
NOTIFYFREE_BATCH_SIZE=10
NOTIFYFREE_BATCH_ENABLED=true
```

### 完整日志通道配置

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'notifyfree'], // 推荐配置
        'ignore_exceptions' => false,
    ],
    
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],
    
    'notifyfree' => [
        'driver' => 'notifyfree',
        'endpoint' => env('NOTIFYFREE_ENDPOINT'),
        'token' => env('NOTIFYFREE_TOKEN'),
        'app_id' => env('NOTIFYFREE_APP_ID'),
        'level' => env('LOG_LEVEL', 'error'),
        'timeout' => env('NOTIFYFREE_TIMEOUT', 30),
        'retry_attempts' => env('NOTIFYFREE_RETRY', 3),
        'bubble' => true,
        
        // 批处理配置（始终启用）
        'batch' => [
            'buffer_size' => env('NOTIFYFREE_BATCH_BUFFER_SIZE', 50),
            'flush_timeout' => env('NOTIFYFREE_BATCH_FLUSH_TIMEOUT', 5),
        ],
        
        // 缓存配置（新功能）
        'cache' => [
            'service_status_enabled' => env('NOTIFYFREE_CACHE_SERVICE_STATUS', true),
            'service_status_ttl' => env('NOTIFYFREE_CACHE_SERVICE_STATUS_TTL', 60),
        ],
        
        'format' => [
            'include_context' => env('NOTIFYFREE_INCLUDE_CONTEXT', true),
            'include_extra' => env('NOTIFYFREE_INCLUDE_EXTRA', true),
        ],
        
        // 废弃配置（向后兼容）
        'batch_size' => env('NOTIFYFREE_BATCH_SIZE', 10), // 请使用 batch.buffer_size
        'batch_enabled' => env('NOTIFYFREE_BATCH_ENABLED', true), // 批处理现在始终启用
    ],
],
```

## 使用方法

### 推荐用法：使用默认 Stack 通道

```php
use Illuminate\Support\Facades\Log;

// 使用默认通道，自动双写到本地文件和远程服务
Log::info('用户登录成功', ['user_id' => 123, 'ip' => request()->ip()]);
Log::error('数据库连接失败', ['database' => 'main', 'error_code' => 'TIMEOUT']);
Log::warning('API 响应缓慢', ['endpoint' => '/api/users', 'response_time' => 3.5]);
```

### 敏感数据过滤

```php
// 敏感数据会被自动过滤
Log::info('用户认证', [
    'username' => 'john_doe',
    'password' => 'secret123',    // 自动过滤为 [FILTERED]
    'token' => 'bearer_xyz',      // 自动过滤为 [FILTERED]
    'email' => 'john@example.com' // 保留
]);
```

### 直接使用 NotifyFree 通道（特殊需求）

```php
// 仅发送到远程服务（不推荐，除非有特殊需求）
Log::channel('notifyfree')->critical('系统故障', [
    'severity' => 'high',
    'component' => 'payment_service'
]);
```

## 工作原理

### Laravel Stack 通道的优势

```
传统方式（不推荐）:
应用 → NotifyFree通道 → 尝试远程发送 → 失败时写fallback文件

Laravel Stack 方式（推荐）:
应用 → Stack通道 → 并行发送到 [Single通道, NotifyFree通道]
                    ↓              ↓
                本地文件        远程服务
```

### 核心优势

1. **并行处理**: 本地写入和远程发送同时进行，不相互阻塞
2. **框架保证**: Laravel 确保即使远程服务失败，本地日志仍然保存
3. **简单可靠**: 无需自定义复杂的 fallback 逻辑
4. **性能优化**: 不需要等待远程响应就能完成本地日志记录

## 测试和验证

### 1. 运行完整测试套件

```bash
php artisan notifyfree:test-log
```

### 2. 测试通道连接

```bash
php artisan tinker --execute="
\$config = config('notifyfree');
\$client = new \\NotifyFree\\LaravelLogger\\Http\\NotifyFreeClient(\$config);
echo \$client->testConnection() ? 'SUCCESS' : 'FAILED';
"
```

### 3. 验证双写功能

```bash
# 清空日志文件
echo '' > storage/logs/laravel.log

# 发送测试日志
php artisan tinker --execute="Log::info('测试双写功能', ['test' => true]);"

# 检查本地日志
tail storage/logs/laravel.log
```

### 4. 直接测试远程批量发送

```bash
php artisan tinker --execute="
\$config = config('notifyfree');
\$client = new \\NotifyFree\\LaravelLogger\\Http\\NotifyFreeClient(\$config);
\$result = \$client->sendBatch([['message' => 'Direct batch test', 'level' => 'info']]);
echo \$result ? 'Remote batch send: SUCCESS' : 'Remote batch send: FAILED';
"
```

## 高级特性

### 智能批处理 + 并发发送

批处理功能默认启用，通过固定长度的缓冲区来优化性能，并使用 Guzzle Promise + curl_multi 实现高性能并发处理：

```php
// 批处理配置（始终启用）
'batch' => [
    'buffer_size' => 50,         // 缓冲区大小（默认50条）
    'flush_timeout' => 5,        // 自动 flush 超时时间（秒）
],
```

**工作原理**：
- 日志条目首先存储在内存缓冲区中（最多50条）
- 当缓冲区达到 `buffer_size` 时自动批量发送
- 批量发送时按每次10条进行分片处理（固定常量）
- **高性能并发**: 使用 Guzzle Promise + curl_multi 并发发送各个 chunk，真正的 I/O 并发
- **广泛兼容**: 支持 PHP 7.4+ 的所有版本，并发处理失败时自动回退到串行模式
- 当超过 `flush_timeout` 时间且缓冲区不为空时，强制发送
- 程序结束时自动清空缓冲区
- 保留每条日志的原始时间戳，而非写入时间

**性能优势**：
- 减少网络请求次数，提高整体性能
- Guzzle Promise 并发发送大幅减少总处理时间
- 基于 curl_multi 的真正 I/O 并发，性能优异
- 广泛的 PHP 版本兼容性，无需特殊扩展
- 被动检查设计，无额外后台进程

### 智能缓存

服务状态缓存减少不必要的连接检查：

```php
// 缓存配置
'cache' => [
    'service_status_enabled' => true,  // 启用服务状态缓存
    'service_status_ttl' => 60,        // 缓存生存时间（秒）
],
```

**功能**：
- 缓存 NotifyFree 服务的连接状态
- 避免频繁的连接测试
- 支持手动缓存失效

### 运行时控制

```php
use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;

// 获取 handler 实例（假设通过依赖注入或其他方式）
$handler = app('log')->channel('notifyfree')->getHandlers()[0];

// 批处理控制（批处理始终启用，但可以调整参数）
$handler->setBatchBufferSize(20);          // 设置缓冲区大小
$handler->setBatchFlushTimeout(10);        // 设置超时时间
$handler->flush();                          // 手动 flush
$handler->clearBuffer();                    // 清空缓冲区

// 缓存控制
$handler->setCacheServiceStatusEnabled(false);  // 禁用状态缓存
$handler->invalidateServiceStatusCache();       // 使缓存失效

// 状态查询
$status = $handler->getServiceStatus();
$handler->logServiceStatus();               // 记录服务状态到错误日志
```

## 性能优化

### 生产环境建议

```env
LOG_CHANNEL=stack
LOG_LEVEL=error              # 只发送重要日志到远程
NOTIFYFREE_TIMEOUT=15        # 减少超时时间
NOTIFYFREE_RETRY=2           # 减少重试次数

# 优化批处理设置（批处理始终启用）
NOTIFYFREE_BATCH_BUFFER_SIZE=100     # 大缓冲区减少网络请求频率
NOTIFYFREE_BATCH_FLUSH_TIMEOUT=30    # 适当增加超时时间

# 优化缓存设置
NOTIFYFREE_CACHE_SERVICE_STATUS=true
NOTIFYFREE_CACHE_SERVICE_STATUS_TTL=300  # 增加缓存时间到5分钟

APP_DEBUG=false
```

### 开发环境建议

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug              # 发送所有日志便于调试
NOTIFYFREE_TIMEOUT=30
NOTIFYFREE_RETRY=3

# 开发环境批处理设置（批处理始终启用）
NOTIFYFREE_BATCH_BUFFER_SIZE=5       # 较小的缓冲区便于快速看到结果
NOTIFYFREE_BATCH_FLUSH_TIMEOUT=3     # 更短的超时时间

# 开发环境缓存设置
NOTIFYFREE_CACHE_SERVICE_STATUS=true
NOTIFYFREE_CACHE_SERVICE_STATUS_TTL=30   # 较短的缓存时间便于测试

APP_DEBUG=true
```

## 许可证

MIT License


### 5. 安全考虑

```php
// 敏感数据自动过滤
'format' => [
    'sensitive_keys' => [
        'password', 'token', 'secret', 'key', 'auth',
        'api_key', 'access_token', 'refresh_token'
    ],
],
```

## 发布配置文件

```bash
# 发布配置文件到 config/notifyfree.php
php artisan vendor:publish --tag=notifyfree-config
```

## 命令行工具

```bash
# 完整功能测试
php artisan notifyfree:test-log

# 清除配置缓存
php artisan config:clear

# 重新发现包
php artisan package:discover
```

## 版本兼容性

| Package | Laravel | PHP     | 特性 |
|---------|---------|---------|------|
| 1.2.x   | 8.0-12.x| 7.4+    | Guzzle Promise 并发，优化批处理 |
| 1.1.x   | 11.x    | 8.2+    | Fiber 并发 |

## 1.2 版本更新说明

### 🚀 主要改进

- **更广泛的兼容性**: 支持 PHP 7.4+ 和 Laravel 8.0+
- **更好的并发性能**: 使用 Guzzle Promise + curl_multi 替代 Fiber，实现真正的 I/O 并发
- **简化的 API**: 移除批处理开关，批处理功能始终启用
- **更稳定的实现**: 基于成熟的 curl_multi 技术，无需特殊扩展

### 🔄 迁移指南

从 1.1 版本升级到 1.2：

1. **环境变量更新**:
   ```env
   # 移除（不再需要）
   # NOTIFYFREE_BATCH_ENABLED=true

   # 保留其他配置不变
   NOTIFYFREE_BATCH_BUFFER_SIZE=50
   NOTIFYFREE_BATCH_FLUSH_TIMEOUT=5
   ```

2. **代码更新**:
   ```php
   // 移除的方法调用
   // $handler->setBatchEnabled(false);  // 不再支持

   // 保留的方法调用
   $handler->setBatchBufferSize(20);     // 仍然支持
   $handler->flush();                    // 仍然支持
   ```

3. **配置文件更新**:
   - 批处理配置中移除 `enabled` 选项
   - 其他配置保持不变

