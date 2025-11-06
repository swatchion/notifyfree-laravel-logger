<?php

namespace NotifyFree\LaravelLogger;

use Illuminate\Support\ServiceProvider;
use Monolog\Level;
use Monolog\Logger;
use NotifyFree\LaravelLogger\Console\Commands\TestNotifyFreeLog;
use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;
use NotifyFree\LaravelLogger\Http\NotifyFreeClient;

class NotifyFreeLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 合并配置文件
        $this->mergeConfigFrom(
            __DIR__.'/../config/notifyfree.php',
            'notifyfree'
        );

        // 注册NotifyFree客户端
        $this->app->singleton(NotifyFreeClient::class, function ($app) {
            $config = $app['config']['notifyfree'] ?? [];

            return new NotifyFreeClient($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__.'/../config/notifyfree.php' => config_path('notifyfree.php'),
        ], 'notifyfree-config');

        // 注册控制台命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestNotifyFreeLog::class,
            ]);
        }

        // 注册日志驱动 - 使用最简单可靠的方式
        $self = $this;
        $this->app->booted(function () use ($self) {
            $logManager = $this->app->make('log');
            $logManager->extend('notifyfree', function ($app, array $config) use ($self) {
                return $self->createNotifyFreeDriver($app, $config);
            });
        });
    }

    /**
     * Create NotifyFree log driver
     */
    public function createNotifyFreeDriver($app, array $config)
    {
        // 获取配置
        $notifyFreeConfig = $app['config']['notifyfree'] ?? [];
        $mergedConfig = array_merge([
            'level' => 'debug',
            'timeout' => 30,
            'retry_attempts' => 3,
            'format' => [
                'include_context' => true,
                'include_extra' => true,
            ],
        ], $notifyFreeConfig, $config);

        // 创建 Monolog Logger
        return new Logger('notifyfree', [
            new NotifyFreeHandler(
                $mergedConfig,
                $this->parseLevel($mergedConfig['level'] ?? 'debug'),
                $mergedConfig['bubble'] ?? true
            ),
        ]);
    }

    /**
     * Parse the string level into a Monolog Level.
     */
    protected function parseLevel($level): int
    {
        $levels = [
            'debug' => Level::Debug->value,
            'info' => Level::Info->value,
            'notice' => Level::Notice->value,
            'warning' => Level::Warning->value,
            'error' => Level::Error->value,
            'critical' => Level::Critical->value,
            'alert' => Level::Alert->value,
            'emergency' => Level::Emergency->value,
        ];

        return $levels[$level] ?? Level::Debug->value;
    }
}
