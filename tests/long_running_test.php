<?php

require_once __DIR__.'/vendor/autoload.php';

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;

echo "=== 真实批处理行为测试 ===\n";
echo "模拟长运行进程，观察批处理和定时 flush\n\n";

// 创建 Handler 实例
$handler = new NotifyFreeHandler(
    'http://127.0.0.1:8000/api/v1/messages',
    '3|MlWq7TrUAxehhjXZZ72Az6TlURD36iEpnjNi7k74',
    '4',
    30, 3, 50, true, true, Logger::DEBUG, true, true
);

echo "配置:\n";
echo "- Buffer 大小限制: 50 条\n";
echo "- Chunk 大小: 10 条/请求\n";
echo "- Flush 超时: 5 秒\n\n";

echo "开始模拟长运行进程...\n\n";

$totalLogs = 0;
$startTime = time();

// 模拟长运行进程，每隔一段时间生成日志
for ($batch = 1; $batch <= 5; $batch++) {
    echo "=== 批次 $batch ===\n";

    // 每批次生成 8-12 条日志
    $batchSize = rand(8, 12);
    echo "生成 $batchSize 条日志...\n";

    for ($i = 1; $i <= $batchSize; $i++) {
        $record = new LogRecord(
            new DateTimeImmutable,
            'long_running_process',
            Level::Error,
            "长运行进程日志 - 批次{$batch}.{$i}",
            [
                'batch' => $batch,
                'sequence' => $i,
                'process_time' => time() - $startTime,
            ]
        );

        $handler->handle($record);
        $totalLogs++;

        echo "  日志 {$i} 已添加，当前缓冲区: ".$handler->getBufferSize()." 条\n";

        // 如果达到 buffer 限制，应该自动 flush
        if ($handler->getBufferSize() >= 50) {
            echo "  ⚠️  缓冲区已满，将触发自动 flush\n";
        }

        // 模拟日志生成间隔
        usleep(100000); // 0.1 秒
    }

    echo "批次 $batch 完成，当前总日志数: $totalLogs，缓冲区: ".$handler->getBufferSize()." 条\n";
    echo "等待 2 秒...\n\n";
    sleep(2);
}

echo "=== 最终状态 ===\n";
echo "总共生成: $totalLogs 条日志\n";
echo '最终缓冲区大小: '.$handler->getBufferSize()." 条\n";
echo "进程即将结束，析构函数将处理剩余的日志...\n";

// 这里进程结束，__destruct() 会处理剩余的日志
