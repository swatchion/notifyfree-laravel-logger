<?php

namespace NotifyFree\LaravelLogChannel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Log\LogManager;
use Monolog\Logger;
use NotifyFree\LaravelLogChannel\Handlers\NotifyFreeHandler;
use NotifyFree\LaravelLogChannel\Http\NotifyFreeClient;
use NotifyFree\LaravelLogChannel\Console\Commands\TestNotifyFreeLog;
use NotifyFree\LaravelLogChannel\Console\Commands\NotifyFreeCacheManager;

class NotifyFreeLogChannelServiceProvider extends ServiceProvider
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

        // 注册NotifyFree客户端单例
        $this->app->singleton(NotifyFreeClient::class, function ($app) {
            $config = $app['config']['notifyfree'];
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
                NotifyFreeCacheManager::class,
            ]);
        }

        // 兼容不同Laravel版本的日志驱动注册方式
        $this->registerLogDriver();
    }

    /**
     * 注册自定义日志驱动，兼容不同Laravel版本
     */
    protected function registerLogDriver(): void
    {
        // Laravel 11+都支持这种方式，但我们需要确保LogManager已经解析
        if ($this->app->resolved('log')) {
            $this->extendLogManager($this->app['log']);
        } else {
            $this->app->afterResolving('log', function ($logManager) {
                $this->extendLogManager($logManager);
            });
        }
    }

    /**
     * 扩展日志管理器
     */
    protected function extendLogManager(LogManager $logManager): void
    {
        $logManager->extend('notifyfree', function ($app, array $config) {
            return $this->createNotifyFreeLogger($config);
        });
    }

    /**
     * 创建NotifyFree日志记录器
     */
    protected function createNotifyFreeLogger(array $config): Logger
    {
        // 合并默认配置和全局配置
        $notifyFreeConfig = $this->app['config']['notifyfree'] ?? [];
        $config = array_merge($this->getDefaultConfig(), $notifyFreeConfig, $config);

        // 从 logging.php 的通道配置中提取 max_file_size
        if (isset($config['max_file_size'])) {
            $config['max_file_size'] = (int) $config['max_file_size'];
        }

        // 验证必需的配置项
        $this->validateConfig($config);

        // 创建处理器
        $handler = $this->createHandler($config);

        // 创建Logger实例
        $logger = new Logger('notifyfree', [$handler]);

        return $logger;
    }

    /**
     * 创建处理器，支持不同类型
     */
    protected function createHandler(array $config): NotifyFreeHandler
    {
        $handlerClass = $config['handler'] ?? NotifyFreeHandler::class;

        // 确保处理器类存在
        if (!class_exists($handlerClass)) {
            throw new \InvalidArgumentException("Handler class {$handlerClass} does not exist");
        }

        // 确保处理器继承自正确的基类
        if (!is_subclass_of($handlerClass, NotifyFreeHandler::class)) {
            throw new \InvalidArgumentException("Handler must extend " . NotifyFreeHandler::class);
        }

        return new $handlerClass($config);
    }

    /**
     * 获取默认配置
     */
    protected function getDefaultConfig(): array
    {
        return [
            'level' => Logger::DEBUG,
            'timeout' => 30,
            'retry_attempts' => 3,
            'batch_size' => 10,
            'path' => storage_path('logs/laravel.log'),
            'format' => [
                'include_context' => true,
                'include_extra' => true,
                'timestamp_format' => 'Y-m-d H:i:s',
                'max_message_length' => 1000,
            ],
        ];
    }

    /**
     * 验证配置的有效性
     */
    protected function validateConfig(array $config): void
    {
        $required = ['endpoint', 'token', 'app_id'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("NotifyFree log channel requires '{$key}' configuration");
            }
        }

        // 验证endpoint格式
        if (!filter_var($config['endpoint'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid NotifyFree endpoint URL: {$config['endpoint']}");
        }
    }

    /**
     * 获取配置发布路径
     */
    public function provides(): array
    {
        return [
            NotifyFreeClient::class,
        ];
    }
}
