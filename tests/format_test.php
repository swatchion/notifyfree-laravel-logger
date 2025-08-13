<?php

require_once __DIR__.'/vendor/autoload.php';

use Monolog\Level;
use Monolog\LogRecord;
use NotifyFree\LaravelLogger\Formatters\NotifyFreeFormatter;

echo "=== NotifyFree 格式化器测试 ===\n\n";

// 创建格式化器
$formatter = new NotifyFreeFormatter([
    'format' => [
        'include_context' => true,
        'include_extra' => true,
        'timestamp_format' => 'c',
        'max_message_length' => 1000,
    ],
]);

// 创建测试日志记录
$record = new LogRecord(
    new DateTimeImmutable,
    'test_channel',
    Level::Info,
    '这是一条测试消息',
    [
        'user_id' => 123,
        'tags' => ['test', 'formatter'],
        'action' => 'format_test',
    ]
);

// 格式化记录
$formatted = $formatter->format($record);

echo "格式化后的数据结构：\n";
echo json_encode($formatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

// 验证字段
echo "字段验证：\n";
echo '- message: '.(isset($formatted['message']) ? '✓' : '✗')."\n";
echo '- level: '.(isset($formatted['level']) ? '✓' : '✗')."\n";
echo '- timestamp: '.(isset($formatted['timestamp']) ? '✓' : '✗')."\n";
echo '- tags: '.(isset($formatted['tags']) ? '✓' : '✗')."\n";
echo '- metadata: '.(isset($formatted['metadata']) ? '✓' : '✗')."\n";

echo "\n字段内容：\n";
echo '- message: '.($formatted['message'] ?? 'N/A')."\n";
echo '- level: '.($formatted['level'] ?? 'N/A')."\n";
echo '- timestamp: '.($formatted['timestamp'] ?? 'N/A')."\n";
echo '- tags: '.json_encode($formatted['tags'] ?? [])."\n";

echo "\n=== 测试完成 ===\n";
