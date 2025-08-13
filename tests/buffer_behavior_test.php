<?php

require_once __DIR__.'/vendor/autoload.php';

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;

echo "=== Buffer 行为测试 ===\n\n";

// 创建 Handler 实例
$handler = new NotifyFreeHandler(
    'http://127.0.0.1:8000/api/v1/messages',
    '3|MlWq7TrUAxehhjXZZ72Az6TlURD36iEpnjNi7k74',
    '4',
    30, 3, 50, true, true, Logger::DEBUG, true, true
);

echo "1. 测试单条日志（模拟 artisan tinker 行为）\n";
echo "   创建新的 handler 实例...\n";

// 添加一条日志
$record = new LogRecord(
    new DateTimeImmutable,
    'test_channel',
    Level::Error,
    '测试单条日志',
    ['test' => 'single']
);

$handler->handle($record);
echo '   ✓ 日志已添加，当前缓冲区大小: '.$handler->getBufferSize()."\n";
echo "   脚本即将结束，__destruct() 将被调用...\n";

// 这里脚本结束，析构函数会被调用，导致 flush

echo "\n";
echo "2. 测试同一进程中的多条日志\n";

// 在同一个进程中添加多条日志
for ($i = 1; $i <= 5; $i++) {
    $record = new LogRecord(
        new DateTimeImmutable,
        'test_channel',
        Level::Error,
        "批量测试消息 {$i}",
        ['test' => 'batch', 'sequence' => $i]
    );

    $handler->handle($record);
    echo "   ✓ 日志 {$i} 已添加，当前缓冲区大小: ".$handler->getBufferSize()."\n";
}

echo '   当前缓冲区大小: '.$handler->getBufferSize()."\n";
echo "   手动 flush 测试...\n";
$handler->flush();
echo '   ✓ 手动 flush 完成，缓冲区大小: '.$handler->getBufferSize()."\n";

echo "\n=== 分析完成 ===\n";
echo "说明：\n";
echo "1. 单独的 artisan tinker 命令会立即触发析构函数\n";
echo "2. 同一进程中的多条日志可以正常缓冲\n";
echo "3. 只有在进程结束或手动 flush 时才会发送\n";
