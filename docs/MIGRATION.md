    // 更多详细信息...
]
*/
```

## 向后兼容性

### 保留的功能

- 所有原有的构造函数参数仍然支持
- 原有的方法调用仍然有效
- 环境变量 `NOTIFYFREE_BATCH_SIZE` 仍可使用（映射到新的配置）

### 废弃的类

以下类已被标记为废弃，但仍可使用：
- `BatchNotifyFreeHandler` → 使用 `NotifyFreeHandler` + 批处理配置
- `CachedNotifyFreeHandler` → 使用 `NotifyFreeHandler` + 缓存配置

## 性能优势

### 批处理改进

- **定时 flush**: 即使缓冲区未满也会定期发送
- **被动检查**: 无额外后台进程，性能开销最小
- **失败处理**: 批量发送失败不会回退到单条发送

### 缓存优化

- **智能缓存**: 减少不必要的连接检查
- **可配置 TTL**: 根据需求调整缓存时间
- **手动控制**: 支持缓存失效和重新检查

## 故障排除

### 常见问题

1. **批处理不工作**
   ```php
   // 检查配置
   $status = $handler->getServiceStatus();
   var_dump($status['batch_enabled']);
   ```

2. **缓存问题**
   ```php
   // 清除缓存
   $handler->invalidateServiceStatusCache();
   ```

3. **迁移后日志不发送**
   ```php
   // 检查配置是否正确
   $handler->logServiceStatus();
   // 查看错误日志获取详细信息
   ```

## 测试迁移

运行测试脚本验证迁移：

```bash
php test_features.php
```

检查输出确保所有功能正常工作。

## 完整示例配置

```php
// config/logging.php
'notifyfree' => [
    'driver' => 'notifyfree',
    'endpoint' => env('NOTIFYFREE_ENDPOINT'),
    'token' => env('NOTIFYFREE_TOKEN'),
    'app_id' => env('NOTIFYFREE_APP_ID'),
    'level' => env('LOG_LEVEL', 'error'),
    'timeout' => env('NOTIFYFREE_TIMEOUT', 30),
    'retry_attempts' => env('NOTIFYFREE_RETRY', 3),
    'bubble' => true,
    
    // 新的批处理配置
    'batch' => [
        'enabled' => env('NOTIFYFREE_BATCH_ENABLED', true),
        'buffer_size' => env('NOTIFYFREE_BATCH_BUFFER_SIZE', 10),
        'flush_timeout' => env('NOTIFYFREE_BATCH_FLUSH_TIMEOUT', 5),
    ],
    
    // 新的缓存配置
    'cache' => [
        'service_status_enabled' => env('NOTIFYFREE_CACHE_SERVICE_STATUS', true),
        'service_status_ttl' => env('NOTIFYFREE_CACHE_SERVICE_STATUS_TTL', 60),
    ],
    
    'format' => [
        'include_context' => env('NOTIFYFREE_INCLUDE_CONTEXT', true),
        'include_extra' => env('NOTIFYFREE_INCLUDE_EXTRA', true),
    ],
],
```

## 推荐设置

### 生产环境
```env
NOTIFYFREE_BATCH_ENABLED=true
NOTIFYFREE_BATCH_BUFFER_SIZE=20
NOTIFYFREE_BATCH_FLUSH_TIMEOUT=10
NOTIFYFREE_CACHE_SERVICE_STATUS=true
NOTIFYFREE_CACHE_SERVICE_STATUS_TTL=300
```

### 开发环境
```env
NOTIFYFREE_BATCH_ENABLED=true
NOTIFYFREE_BATCH_BUFFER_SIZE=5
NOTIFYFREE_BATCH_FLUSH_TIMEOUT=3
NOTIFYFREE_CACHE_SERVICE_STATUS=true
NOTIFYFREE_CACHE_SERVICE_STATUS_TTL=30
```

迁移完成后，您将获得更强大、更灵活的日志处理功能！
