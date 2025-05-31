<?php

// 完全模拟您提供的 curl 示例
$url = 'http://127.0.0.1:8000/api/v1/messages/batch';
$token = '3|MlWq7TrUAxehhjXZZ72Az6TlURD36iEpnjNi7k74';

// 完全按照您的示例格式
$data = [
    'messages' => [
        [
            'message' => '批量消息5 - 高性能写入',
            'level' => 'info',
            'tags' => ['batch', 'performance', 'test1'],
            'metadata' => [
                'batch_id' => 'batch_001',
                'sequence' => 1,
                'source' => 'performance_test'
            ],
            'timestamp' => '2023-01-01T10:00:00Z'
        ],
        [
            'message' => '批量消息4 - InfluxDB批量写入',
            'level' => 'warn', 
            'tags' => ['batch', 'influxdb', 'test2'],
            'metadata' => [
                'batch_id' => 'batch_001',
                'sequence' => 2,
                'source' => 'performance_test'
            ]
        ],
        [
            'message' => '批量消息6 - 多应用同步',
            'level' => 'error',
            'tags' => ['batch', 'multi-app', 'test3'],
            'metadata' => [
                'batch_id' => 'batch_001', 
                'sequence' => 3,
                'source' => 'performance_test'
            ]
        ]
    ]
];

$headers = [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

echo "发送您提供的示例数据:\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP 状态码: $httpCode\n";
echo "响应内容: $response\n";

if ($httpCode === 200) {
    echo "\n✅ 您的示例数据发送成功！\n";
} else {
    echo "\n❌ 发送失败\n";
}

curl_close($ch);
