<?php

/**
 * NotifyFree Handler 功能测试脚本
 * 
 * 此脚本用于测试合并后的 NotifyFreeHandler 的批处理和缓存功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Level;

echo "=== NotifyFree Handler 功能测试 ===\n\n";

// 1. 创建 Handler 实例
echo "1. 创建 Handler 实例...\n";
$handler = new NotifyFreeHandler(
    'http://127.0.0.1:8000/api/v1/messages',
    '3|MlWq7TrUAxehhjXZZ72Az6TlURD36iEpnjNi7k74',  // 使用 .env 中的正确 token
    '4',  // 使用 .env 中的正确 app_id
    30, // timeout
    3,  // retry_attempts
    50, // batch_size (updated to match new default)
    true, // include_context
    true, // include_extra
    Logger::DEBUG,
    true,
    true  // fallback_enabled
);

echo "✓ Handler 创建成功\n\n";

// 2. 测试批处理功能
echo "2. 测试批处理功能...\n";

// 检查初始状态
echo "   初始缓冲区大小: " . $handler->getBufferSize() . "\n";

// 获取服务状态
$status = $handler->getServiceStatus();
echo "   批处理启用: " . ($status['batch_enabled'] ? 'Yes' : 'No') . "\n";
echo "   缓冲区大小限制: " . $status['batch_buffer_size'] . "\n";
echo "   Flush 超时: " . $status['batch_flush_timeout'] . " 秒\n";
echo "   Chunk 大小: " . $status['batch_chunk_size'] . " 条/请求\n";
echo "   Fiber 支持: " . ($status['fiber_support'] ? 'Yes (并发处理)' : 'No (串行处理)') . "\n";
echo "   并发状态: " . $status['concurrent_processing'] . "\n";

// 模拟添加日志记录
echo "\n   添加测试日志记录...\n";
for ($i = 1; $i <= 5; $i++) {
    $record = new LogRecord(
        new DateTimeImmutable(),
        'test_channel',
        Level::Info,
        "测试消息 {$i}",
        ['test_data' => $i]
    );
    
    try {
        $handler->handle($record);
        echo "   ✓ 日志 {$i} 已添加，当前缓冲区大小: " . $handler->getBufferSize() . "\n";
    } catch (Exception $e) {
        echo "   ✗ 日志 {$i} 添加失败: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 3. 测试手动 flush
echo "3. 测试手动 flush...\n";
echo "   Flush 前缓冲区大小: " . $handler->getBufferSize() . "\n";
try {
    $handler->flush();
    echo "   ✓ 手动 flush 成功\n";
    echo "   Flush 后缓冲区大小: " . $handler->getBufferSize() . "\n";
} catch (Exception $e) {
    echo "   ✗ 手动 flush 失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. 测试批处理配置更改
echo "4. 测试批处理配置更改...\n";
$handler->setBatchBufferSize(3);
$handler->setBatchFlushTimeout(2);

$status = $handler->getServiceStatus();
echo "   新的缓冲区大小限制: " . $status['batch_buffer_size'] . "\n";
echo "   新的 Flush 超时: " . $status['batch_flush_timeout'] . " 秒\n";

echo "\n";

// 5. 测试缓存功能
echo "5. 测试缓存功能...\n";
$status = $handler->getServiceStatus();
echo "   服务状态缓存启用: " . ($status['cache_enabled'] ? 'Yes' : 'No') . "\n";
echo "   缓存 TTL: " . $status['cache_ttl'] . " 秒\n";

// 测试连接
echo "   测试服务连接...\n";
try {
    $isConnected = $handler->testConnection();
    echo "   连接状态: " . ($isConnected ? '✓ 连接成功' : '✗ 连接失败') . "\n";
} catch (Exception $e) {
    echo "   连接测试失败: " . $e->getMessage() . "\n";
}

// 记录服务状态
echo "   记录服务状态到错误日志...\n";
$handler->logServiceStatus();
echo "   ✓ 状态已记录到错误日志\n";

echo "\n";

// 6. 测试禁用功能
echo "6. 测试禁用功能...\n";

// 禁用批处理
$handler->setBatchEnabled(false);
echo "   ✓ 批处理已禁用\n";

// 禁用缓存
$handler->setCacheServiceStatusEnabled(false);
echo "   ✓ 服务状态缓存已禁用\n";

$status = $handler->getServiceStatus();
echo "   当前批处理状态: " . ($status['batch_enabled'] ? 'Enabled' : 'Disabled') . "\n";
echo "   当前缓存状态: " . ($status['cache_enabled'] ? 'Enabled' : 'Disabled') . "\n";

echo "\n";

// 7. 测试并发处理能力
echo "7. 测试并发处理能力...\n";
if (class_exists('\Fiber')) {
    echo "   ✓ Fiber 支持可用，测试并发批量发送...\n";
    
    // 重新启用批处理
    $handler->setBatchEnabled(true);
    $handler->setBatchBufferSize(30); // 设置较大的 buffer 以容纳所有记录
    
    // 添加大量日志记录以触发多个 chunk 的并发发送
    echo "   添加大量日志记录以测试并发...\n";
    $startTime = microtime(true);
    
    for ($i = 1; $i <= 25; $i++) {
        $record = new LogRecord(
            new DateTimeImmutable(),
            'test_channel',
            Level::Info,
            "并发测试消息 {$i}",
            ['batch_test' => true, 'message_id' => $i]
        );
        $handler->handle($record);
    }
    
    echo "   当前缓冲区大小: " . $handler->getBufferSize() . "\n";
    echo "   手动触发 flush 以测试并发处理...\n";
    
    $flushStart = microtime(true);
    $handler->flush();
    $flushEnd = microtime(true);
    
    $processingTime = microtime(true) - $startTime;
    $flushTime = ($flushEnd - $flushStart) * 1000;
    
    echo "   ✓ 25条日志记录处理完成，总耗时: " . round($processingTime * 1000, 2) . "ms\n";
    echo "   ✓ Flush 耗时: " . round($flushTime, 2) . "ms\n";
    echo "   预期: 产生3个chunk (10+10+5)，并发发送\n";
} else {
    echo "   ⚠ Fiber 不可用 (PHP < 8.1)，将使用串行处理\n";
}

echo "\n";

// 8. 测试清理
echo "8. 测试清理...\n";
$handler->clearBuffer();
$handler->invalidateServiceStatusCache();
echo "   ✓ 缓冲区已清空\n";
echo "   ✓ 服务状态缓存已失效\n";

echo "\n=== 测试完成 ===\n";
echo "请检查错误日志以查看 NotifyFree 服务状态和发送失败记录。\n";
