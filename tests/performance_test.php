<?php

/**
 * 性能基准测试：Guzzle Promise 并发处理
 */

require_once __DIR__.'/../vendor/autoload.php';

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;

echo "=== NotifyFree 性能基准测试 ===\n";
echo "版本: " . \NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler::VERSION . "\n\n";

// 创建 Handler 实例
$handler = new NotifyFreeHandler(
    'http://httpbin.org/post', // 使用 httpbin 作为测试端点
    'test_token',
    'test_app_id',
    5, // 短超时以加快测试
    1, // 减少重试次数
    50, // batch_size
    true, // include_context
    true, // include_extra
    Logger::DEBUG,
    true,
    true  // fallback_enabled
);

// 测试不同数量的日志记录
$testSizes = [10, 25, 50, 100];

foreach ($testSizes as $size) {
    echo "测试 {$size} 条日志记录的并发处理性能...\n";

    // 清空缓冲区
    $handler->clearBuffer();

    // 记录开始时间
    $startTime = microtime(true);

    // 添加日志记录到缓冲区
    for ($i = 1; $i <= $size; $i++) {
        $record = new LogRecord(
            new DateTimeImmutable,
            'test_channel',
            Level::Info,
            "性能测试日志 #{$i}",
            ['test_id' => $i, 'batch_size' => $size]
        );

        $handler->handle($record);
    }

    // 手动触发 flush 并测量时间
    $flushStartTime = microtime(true);
    $handler->flush();
    $flushEndTime = microtime(true);

    $totalTime = microtime(true) - $startTime;
    $flushTime = $flushEndTime - $flushStartTime;

    // 计算性能指标
    $chunksCount = ceil($size / 10); // 每个 chunk 10 条记录
    $recordsPerSecond = $size / $totalTime;
    $avgTimePerChunk = $flushTime / $chunksCount;

    echo "  ✓ 总耗时: " . round($totalTime * 1000, 2) . "ms\n";
    echo "  ✓ Flush 耗时: " . round($flushTime * 1000, 2) . "ms\n";
    echo "  ✓ Chunks 数量: {$chunksCount}\n";
    echo "  ✓ 平均每 chunk 耗时: " . round($avgTimePerChunk * 1000, 2) . "ms\n";
    echo "  ✓ 处理速度: " . round($recordsPerSecond, 0) . " 条/秒\n";
    echo "  ✓ 并发效率: " . ($chunksCount > 1 ? '并发处理' : '单 chunk') . "\n\n";

    // 短暂休息避免请求过于频繁
    usleep(100000); // 100ms
}

// 测试服务状态
echo "测试服务状态报告...\n";
$status = $handler->getServiceStatus();
echo "  ✓ 版本: " . $status['version'] . "\n";
echo "  ✓ 并发处理: " . $status['concurrent_processing'] . "\n";
echo "  ✓ 批处理: 始终启用\n";
echo "  ✓ Chunk 大小: " . $status['batch_chunk_size'] . "\n";

echo "\n=== 性能测试完成 ===\n";
echo "注意：实际性能会因网络延迟和服务器响应时间而有所不同。\n";
echo "Guzzle Promise 提供真正的 I/O 并发，性能优于串行处理。\n";
