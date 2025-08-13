<?php

// 直接测试 API 调用
$url = 'http://127.0.0.1:8000/api/v1/messages/batch';
$token = '3|MlWq7TrUAxehhjXZZ72Az6TlURD36iEpnjNi7k74';

$data = [
    'messages' => [
        [
            'message' => '测试批量消息1',
            'level' => 'info',
            'tags' => ['test', 'batch'],
            'metadata' => [
                'source' => 'php_test',
                'test_id' => 1,
            ],
            'timestamp' => date('c'),
        ],
    ],
];

$headers = [
    'Authorization: Bearer '.$token,
    'Content-Type: application/json',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

echo "发送数据:\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP 状态码: $httpCode\n";
echo "响应内容: $response\n";

curl_close($ch);
