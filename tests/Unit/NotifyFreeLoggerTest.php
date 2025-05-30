<?php

namespace NotifyFree\LaravelLogChannel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Monolog\Logger as Monolog;
use NotifyFree\LaravelLogChannel\NotifyFreeLogger;
use NotifyFree\LaravelLogChannel\Handlers\NotifyFreeHandler;

class NotifyFreeLoggerTest extends TestCase
{
    public function test_create_driver_returns_monolog_instance()
    {
        $config = [
            'endpoint' => 'https://test.notifyfree.com/api/logs',
            'token' => 'test-token',
            'app_id' => 'test-app-id',
            'level' => 'debug',
            'timeout' => 30,
            'retry_attempts' => 3,
            'batch_size' => 10,
        ];

        $logger = NotifyFreeLogger::createDriver($config);

        $this->assertInstanceOf(Monolog::class, $logger);
        $this->assertEquals('notifyfree', $logger->getName());
    }

    public function test_create_driver_with_custom_channel_name()
    {
        $config = [
            'name' => 'custom-notifyfree',
            'endpoint' => 'https://test.notifyfree.com/api/logs',
            'token' => 'test-token',
            'app_id' => 'test-app-id',
        ];

        $logger = NotifyFreeLogger::createDriver($config);

        $this->assertEquals('custom-notifyfree', $logger->getName());
    }

    public function test_create_driver_with_processors()
    {
        $config = [
            'endpoint' => 'https://test.notifyfree.com/api/logs',
            'token' => 'test-token',
            'app_id' => 'test-app-id',
            'replace_placeholders' => true,
        ];

        $logger = NotifyFreeLogger::createDriver($config);

        // 验证处理器是否正确设置
        $processors = $logger->getProcessors();
        $this->assertCount(1, $processors);
        $this->assertInstanceOf(\Monolog\Processor\PsrLogMessageProcessor::class, $processors[0]);
    }

    public function test_create_driver_with_invalid_log_level_throws_exception()
    {
        $config = [
            'endpoint' => 'https://test.notifyfree.com/api/logs',
            'token' => 'test-token',
            'app_id' => 'test-app-id',
            'level' => 'invalid-level',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level');

        NotifyFreeLogger::createDriver($config);
    }

    public function test_create_driver_handlers_configuration()
    {
        $config = [
            'endpoint' => 'https://test.notifyfree.com/api/logs',
            'token' => 'test-token',
            'app_id' => 'test-app-id',
            'level' => 'warning',
            'bubble' => false,
            'include_context' => false,
            'include_extra' => false,
            'fallback_enabled' => false,
        ];

        $logger = NotifyFreeLogger::createDriver($config);
        $handlers = $logger->getHandlers();

        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(NotifyFreeHandler::class, $handlers[0]);

        // 验证处理器已创建成功
        $this->assertTrue($handlers[0] instanceof NotifyFreeHandler);
    }
}
