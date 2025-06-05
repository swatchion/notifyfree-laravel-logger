<?php

// 在同一个命令中生成多条日志
$commands = [];
for ($i = 1; $i <= 15; $i++) {
    $commands[] = "Log::error('批量测试消息 $i');";
}

$batchCommand = implode(' ', $commands);

echo "将执行批量日志命令：\n";
echo "php artisan tinker --execute=\"$batchCommand\"\n";
