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

## 系统要求

- PHP 8.2+
- Laravel 11.0+
- Monolog 3.0+
- Guzzle HTTP 7.0+

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
    'channels' => ['single', 'notifyfree'],
],
'notifyfree' => [
    'driver' => 'notifyfree',
    'level' => env('LOG_LEVEL', 'error'),
],
```
yfree'], // 本地 + 远程并行
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
NOTIFYFREE_BATCH_SIZE=10
NOTIFYFREE_INCLUDE_CONTEXT=true
NOTIFYFREE_INCLUDE_EXTRA=true
NOTIFYFREE_TIMESTAMP_FORMAT="Y-m-d H:i:s"
NOTIFYFREE_MAX_MESSAGE_LENGTH=1000
LOG_LEVEL=debug
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
        'batch_size' => env('NOTIFYFREE_BATCH_SIZE', 10),
        'bubble' => true,
        'format' => [
            'include_context' => env('NOTIFYFREE_INCLUDE_CONTEXT', true),
            'include_extra' => env('NOTIFYFREE_INCLUDE_EXTRA', true),
        ],
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
\$client = new \\NotifyFree\\LaravelLogChannel\\Http\\NotifyFreeClient(\$config);
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

### 4. 直接测试远程发送

```bash
php artisan tinker --execute="
\$config = config('notifyfree');
\$client = new \\NotifyFree\\LaravelLogChannel\\Http\\NotifyFreeClient(\$config);
\$result = \$client->send(['message' => 'Direct test', 'level' => 'info']);
echo \$result ? 'Remote send: SUCCESS' : 'Remote send: FAILED';
"
```

## 故障排除

### 常见问题

#### 1. "Driver [notifyfree] is not supported"

**原因**：日志驱动未正确注册或缓存问题

**解决方案**：
```bash
# 清除所有缓存
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 检查服务提供者是否加载
php artisan tinker --execute="dd(array_keys(app()->getLoadedProviders()));" | grep NotifyFree
```

**如果问题持续存在**：
```bash
# 重新发现包
php artisan package:discover

# 检查配置文件
php artisan tinker --execute="dd(config('logging.channels.notifyfree'));"
```

#### 2. 内存耗尽或无限循环

**症状**：`PHP Fatal error: Allowed memory size exhausted`

**原因**：日志驱动配置中可能存在循环依赖

**解决方案**：
1. 检查日志配置中是否有循环引用
2. 临时禁用 stack 通道中的 notifyfree，只使用 single 通道测试
3. 确保 NotifyFree 处理器没有调用其他日志通道

```php
// 临时测试配置
'stack' => [
    'channels' => ['single'], // 先只用单一通道测试
],
```

#### 3. 远程发送失败但无错误提示

**解决方案**：
```bash
# 测试网络连接
curl -X POST http://127.0.0.1:8000/api/v1/messages \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"message":"test"}'

# 验证配置
php artisan tinker --execute="dd(config('notifyfree'));"

# 测试连接
php artisan tinker --execute="
\$client = new \\NotifyFree\\LaravelLogChannel\\Http\\NotifyFreeClient(config('notifyfree'));
echo \$client->testConnection() ? 'SUCCESS' : 'FAILED';
"
```

#### 4. 本地日志权限问题

```bash
# 检查权限
ls -la storage/logs/

# 修复权限
chmod 775 storage/logs/
chown -R www-data:www-data storage/logs/  # Linux
# 或
chown -R _www:_www storage/logs/          # macOS
```

## 性能优化

### 生产环境建议

```env
LOG_CHANNEL=stack
LOG_LEVEL=error              # 只发送重要日志到远程
NOTIFYFREE_TIMEOUT=15        # 减少超时时间
NOTIFYFREE_RETRY=2           # 减少重试次数
APP_DEBUG=false
```

### 开发环境建议

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug              # 发送所有日志便于调试
NOTIFYFREE_TIMEOUT=30
NOTIFYFREE_RETRY=3
APP_DEBUG=true
```

## 最佳实践

### 1. 推荐配置模式

**Stack 通道配置（推荐）**
```php
'stack' => [
    'driver' => 'stack',
    'channels' => ['single', 'notifyfree'], // 本地 + 远程
],
```

**优势：**
- 框架级别的可靠性保证
- 并行写入，性能更好
- 配置简单，维护成本低
- 无需自定义 fallback 逻辑

### 2. 日志级别策略

```php
// 本地记录所有日志
'single' => ['level' => 'debug'],

// 远程只发送重要日志
'notifyfree' => ['level' => 'error'],
```

### 3. 监控建议

- 定期检查 `storage/logs/laravel.log` 中的发送失败记录
- 监控远程服务的可用性和响应时间
- 设置日志文件轮转避免文件过大

### 4. 调试和故障排除策略

**渐进式测试方法**：
```php
// 1. 先测试单一通道
'default' => env('LOG_CHANNEL', 'single'),

// 2. 再测试 notifyfree 通道
Log::channel('notifyfree')->info('单独测试');

// 3. 最后启用 stack 通道
'default' => env('LOG_CHANNEL', 'stack'),
```

**监控和调试**：
```php
// 启用详细错误信息
APP_DEBUG=true
LOG_LEVEL=debug

// 检查驱动注册状态
php artisan tinker --execute="
\$logManager = app('log');
\$reflection = new ReflectionClass(\$logManager);
\$customCreators = \$reflection->getProperty('customCreators');
\$customCreators->setAccessible(true);
echo isset(\$customCreators->getValue(\$logManager)['notifyfree']) ? 'REGISTERED' : 'NOT REGISTERED';
"
```

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

| Package | Laravel | PHP     |
|---------|---------|---------|
| 1.0.x   | 11.x    | 8.2+    |

## 更新日志

### v1.1.1 (最新)
- **重大修复**：解决服务提供者注册中的闭包上下文问题
- **性能优化**：修复可能导致内存耗尽的循环依赖问题
- **稳定性提升**：使用 `app->booted()` 确保驱动在正确时机注册
- **代码简化**：重构服务提供者，移除不必要的复杂逻辑
- **改进调试**：增强故障排除文档，添加内存问题和循环依赖的解决方案

### v1.1.0
- 改进服务提供者注册机制，解决 tinker 环境兼容性
- 修复连接测试方法，使用更通用的端点测试
- 更新文档，强调 Laravel Stack 通道最佳实践
- 简化测试命令输出，移除误导性 fallback 文件引用

### v1.0.0
- 初始版本发布
- 支持基本的远程日志发送功能
- 集成 Laravel Stack 通道
- 敏感数据过滤功能

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

| Package | Laravel | PHP     |
|---------|---------|---------|
| 1.1.x   | 11.x    | 8.2+    |

