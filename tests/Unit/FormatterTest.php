<?php

namespace NotifyFree\LaravelLogger\Tests\Unit;

use Monolog\Level;
use Monolog\LogRecord;
use NotifyFree\LaravelLogger\Formatters\NotifyFreeFormatter;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{
    protected NotifyFreeFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'include_context' => true,
            'include_extra' => true,
            'timestamp_format' => 'Y-m-d H:i:s',
            'max_message_length' => 100,
            'sensitive_keys' => ['password', 'token', 'secret'],
        ];

        $this->formatter = new NotifyFreeFormatter(['format' => $config]);
    }

    public function test_can_format_basic_log_record()
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: ['key' => 'value'],
            extra: []
        );

        $formatted = $this->formatter->format($record);

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('message', $formatted);
        $this->assertArrayHasKey('level', $formatted);
        $this->assertArrayHasKey('timestamp', $formatted);
        $this->assertEquals('Test message', $formatted['message']);
    }
}
