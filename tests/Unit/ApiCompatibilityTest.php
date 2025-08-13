<?php

namespace NotifyFree\LaravelLogger\Tests\Unit;

use Monolog\Level;
use Monolog\LogRecord;
use NotifyFree\LaravelLogger\Formatters\NotifyFreeFormatter;
use PHPUnit\Framework\TestCase;

class ApiCompatibilityTest extends TestCase
{
    protected NotifyFreeFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'include_context' => true,
            'include_extra' => true,
            'timestamp_format' => 'Y-m-d H:i:s',
            'max_message_length' => 1000,
        ];

        $this->formatter = new NotifyFreeFormatter(['format' => $config]);
    }

    public function test_formatted_data_matches_server_api_contract()
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2025-05-30 11:30:45'),
            channel: 'notifyfree',
            level: Level::Error,
            message: 'Test error message',
            context: ['user_id' => 123, 'tags' => ['urgent', 'api']],
            extra: ['request_id' => 'req_123']
        );

        $formatted = $this->formatter->format($record);

        // 验证必需字段
        $this->assertArrayHasKey('message', $formatted);
        $this->assertArrayHasKey('level', $formatted);

        // 验证字段格式
        $this->assertIsString($formatted['message']);
        $this->assertIsString($formatted['level']);
        $this->assertEquals('error', $formatted['level']); // 应该是字符串而不是数字

        // 验证可选字段
        $this->assertArrayHasKey('metadata', $formatted);
        $this->assertArrayHasKey('tags', $formatted);

        // 验证 tags 格式
        $this->assertIsArray($formatted['tags']);
        $this->assertEquals(['urgent', 'api'], $formatted['tags']);

        // 验证 metadata 结构
        $this->assertIsArray($formatted['metadata']);
        $this->assertArrayHasKey('context', $formatted['metadata']);
        $this->assertArrayHasKey('extra', $formatted['metadata']);
        $this->assertArrayHasKey('channel', $formatted['metadata']);
        $this->assertArrayHasKey('timestamp', $formatted['metadata']);
    }
}
