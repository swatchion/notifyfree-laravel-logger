# NotifyFree Laravel Log Channel

这是一个用于Laravel的NotifyFree日志通道扩展包，允许应用程序同时向本地日志文件和NotifyFree远程服务发送日志数据。

## 功能特性

- 🔄 **双写功能**: 同时写入本地日志文件和远程NotifyFree服务
- 🔐 **Token认证**: 通过API Token进行安全认证
- 📦 **批量发送**: 支持批量日志处理以提高性能
- 💾 **本地备份**: 网络故障时自动保存到本地文件
- 🎛️ **可配置**: 丰富的配置选项满足不同需求
- 🛡️ **敏感数据过滤**: 自动过滤密码、Token等敏感信息

## 系统要求

- PHP 8.2+
- Laravel 11.0+
- Monolog 3.0+
- Guzzle HTTP 7.0+

## 安装

### 1. 本地开发安装

在主项目中，包已经通过 `composer.json` 的 autoload 配置自动加载。

### 2. 发布配置文件

```bash
php artisan vendor:publish --provider="NotifyFree\LaravelLogChannel\NotifyFreeLogChannelServiceProvider" --tag="notifyfree-log-config"
```

### 3. 环境变量配置

在 `.env` 文件中添加以下配置：

```env
NOTIFYFREE_LOG_ENDPOINT=https://api.notifyfree.com/v1/logs
NOTIFYFREE_LOG_TOKEN=your-api-token
NOTIFYFREE_APPLICATION_ID=your-application-id

# 可选配置
NOTIFYFREE_LOG_TIMEOUT=30
NOTIFYFREE_LOG_RETRY=3
NOTIFYFREE_LOG_FALLBACK_ENABLED=true
```

### 4. 配置日志通道

在 `config/logging.php` 中添加 notifyfree 通道：

```php
'channels' => [
    'notifyfree' => [
        'driver' => 'notifyfree',
        'level' => env('LOG_LEVEL', 'debug'),
    ],
    
    // 或者配置为默认通道
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'notifyfree'],
    ],
],
```

## 使用方法

### 基本用法

```php
use Illuminate\Support\Facades\Log;

// 使用notifyfree通道记录日志
Log::channel('notifyfree')->info('用户登录成功', ['user_id' => 123]);
Log::channel('notifyfree')->error('数据库连接失败', ['error' => $exception->getMessage()]);

// 或者设置为默认通道
Log::info('这条日志会同时写入本地文件和NotifyFree');
```

### 高级配置

可以在 `config/notifyfree-log.php` 中进行详细配置：

```php
return [
    'endpoint' => env('NOTIFYFREE_LOG_ENDPOINT'),
    'token' => env('NOTIFYFREE_LOG_TOKEN'),
    'application_id' => env('NOTIFYFREE_APPLICATION_ID'),
    
    'format' => [
        'include_context' => true,
        'include_extra' => true,
        'max_message_length' => 1000,
        'sensitive_keys' => ['password', 'token', 'secret'],
    ],
    
    'fallback' => [
        'enabled' => true,
        'local_storage_path' => storage_path('logs/notifyfree-fallback.log'),
    ],
];
```

## 工作原理

1. **双写机制**: 日志首先由Laravel的标准机制写入本地文件
2. **远程发送**: 同时异步发送到NotifyFree服务
3. **故障转移**: 如果远程发送失败，日志保存到本地备份文件
4. **数据过滤**: 自动过滤敏感信息，保护隐私安全

## 开发计划

- [x] 基础日志通道功能
- [x] HTTP客户端集成
- [x] 配置管理
- [x] 错误处理和本地备份
- [ ] 批量处理Handler
- [ ] 队列异步发送
- [ ] 完整测试套件
- [ ] 性能优化

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request来帮助改进这个包。
