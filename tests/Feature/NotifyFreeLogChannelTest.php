<?php

namespace NotifyFree\LaravelLogChannel\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Log;
use NotifyFree\LaravelLogChannel\NotifyFreeLoggerServiceProvider;

class NotifyFreeLogChannelTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [NotifyFreeLoggerServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('notifyfree.endpoint', 'https://test.notifyfree.com/api/v1/messages');
        $app['config']->set('notifyfree.token', 'test-token');
        $app['config']->set('notifyfree.app_id', 'test-app-id');
    }

    public function test_can_register_notifyfree_log_driver()
    {
        config(['logging.channels.notifyfree' => [
            'driver' => 'notifyfree',
            'level' => 'error',
        ]]);

        $logger = Log::channel('notifyfree');
        $this->assertInstanceOf(\Illuminate\Log\Logger::class, $logger);
    }

    public function test_can_use_stack_channel_with_notifyfree()
    {
        config([
            'logging.channels.stack' => [
                'driver' => 'stack',
                'channels' => ['single', 'notifyfree'],
            ],
            'logging.channels.notifyfree' => [
                'driver' => 'notifyfree',
                'level' => 'error',
            ],
            'logging.channels.single' => [
                'driver' => 'single',
                'path' => storage_path('logs/test.log'),
            ]
        ]);

        Log::channel('stack')->info('测试 stack 通道');
        $this->assertTrue(true);
    }

    public function test_notifyfree_handles_different_log_levels()
    {
        config(['logging.channels.notifyfree' => [
            'driver' => 'notifyfree',
            'level' => 'debug',
        ]]);

        $logger = Log::channel('notifyfree');

        // 测试不同级别的日志
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');

        $this->assertTrue(true);
    }
}
