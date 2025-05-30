<?php

namespace NotifyFree\LaravelLogger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;
use NotifyFree\LaravelLogger\Handlers\BatchNotifyFreeHandler;
use NotifyFree\LaravelLogger\Handlers\CachedNotifyFreeHandler;

class HandlersTest extends TestCase
{
    protected array $testConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testConfig = [
            'endpoint' => 'https://test.notifyfree.com/api/v1/messages',
            'token' => 'test-token',
            'app_id' => 'test-app-id',
            'timeout' => 30,
            'retry_attempts' => 3,
            'batch_size' => 5,
            'format' => [
                'include_context' => true,
                'include_extra' => true,
                'timestamp_format' => 'Y-m-d H:i:s',
                'max_message_length' => 1000,
            ],
        ];
    }

    public function test_notify_free_handler_can_be_created()
    {
        $handler = new NotifyFreeHandler($this->testConfig);
        $this->assertInstanceOf(NotifyFreeHandler::class, $handler);
    }

    public function test_batch_notify_free_handler_can_be_created()
    {
        $handler = new BatchNotifyFreeHandler($this->testConfig);
        $this->assertInstanceOf(BatchNotifyFreeHandler::class, $handler);
        $this->assertEquals(0, $handler->getBufferSize());
    }

    public function test_cached_notify_free_handler_can_be_created()
    {
        $handler = new CachedNotifyFreeHandler($this->testConfig);
        $this->assertInstanceOf(CachedNotifyFreeHandler::class, $handler);
    }

    public function test_batch_handler_buffer_operations()
    {
        $handler = new BatchNotifyFreeHandler($this->testConfig);
        
        // 初始缓冲区为空
        $this->assertEquals(0, $handler->getBufferSize());
        
        // 清空缓冲区
        $handler->clearBuffer();
        $this->assertEquals(0, $handler->getBufferSize());
    }
}
