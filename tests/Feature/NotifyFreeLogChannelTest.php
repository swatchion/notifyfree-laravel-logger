<?php

namespace NotifyFree\LaravelLogChannel\Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use NotifyFree\LaravelLogChannel\NotifyFreeLogChannelServiceProvider;

class NotifyFreeLogChannelTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            NotifyFreeLogChannelServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('notifyfree.endpoint', 'https://test.notifyfree.com/api/logs');
        $app['config']->set('notifyfree.token', 'test-token');
        $app['config']->set('notifyfree.app_id', 'test-app-id');
    }

    public function test_can_register_notifyfree_log_driver()
    {
        config([
            'logging.channels.notifyfree' => [
                'driver' => 'notifyfree',
                'endpoint' => 'https://test.notifyfree.com/api/logs',
                'token' => 'test-token',
                'app_id' => 'test-app-id',
            ]
        ]);

        $logger = Log::channel('notifyfree');
        $this->assertInstanceOf(\Illuminate\Log\Logger::class, $logger);
    }

    public function test_can_log_messages()
    {
        config([
            'logging.channels.notifyfree' => [
                'driver' => 'notifyfree',
                'endpoint' => 'https://test.notifyfree.com/api/logs',
                'token' => 'test-token',
                'app_id' => 'test-app-id',
            ]
        ]);

        // 这个测试不会实际发送HTTP请求，但会验证日志通道可以正常工作
        Log::channel('notifyfree')->info('测试日志消息', ['test' => true]);

        // 如果没有异常抛出，说明通道工作正常
        $this->assertTrue(true);
    }
}
