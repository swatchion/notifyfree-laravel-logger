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

        // 注册NotifyFree客户端 - 延迟实例化
        $this->app->singleton(NotifyFreeClient::class, function ($app) {
            $config = $app['config']['notifyfree'] ?? [];

            // 如果配置不完整，返回一个空的mock客户端
            if (!$this->validateConfig($config)) {
                return new class {
                    public function __call($method, $args) {
                        return null;
                    }
                };
            }

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

        // 注册日志驱动 - 必须在boot中立即注册
        $this->registerLogDriver();
    }

    /**
     * 注册自定义日志驱动
     */
    protected function registerLogDriver(): void
    {
        $logManager = $this->app->make('log');

        $logManager->extend('notifyfree', function ($app, array $config) {
            return $this->createNotifyFreeDriver($config);
        });
    }

    /**
     * Create an instance of the NotifyFree log driver.
     * This method follows the same pattern as Laravel's createSlackDriver method.
     *
     * @param  array  $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createNotifyFreeDriver(array $config)
    {
        // 合并配置
        $notifyFreeConfig = $this->app['config']['notifyfree'] ?? [];
        $mergedConfig = array_merge($this->getDefaultConfig(), $notifyFreeConfig, $config);

        return new \Monolog\Logger($this->parseChannel($mergedConfig), [
            $this->prepareHandler(new NotifyFreeHandler(
                $mergedConfig['endpoint'] ?? '',
                $mergedConfig['token'] ?? '',
                $mergedConfig['app_id'] ?? '',
                $mergedConfig['timeout'] ?? 30,
                $mergedConfig['retry_attempts'] ?? 3,
                $mergedConfig['batch_size'] ?? 10,
                $mergedConfig['format']['include_context'] ?? true,
                $mergedConfig['format']['include_extra'] ?? true,
                $this->level($mergedConfig),
                $mergedConfig['bubble'] ?? true,
                $mergedConfig['fallback']['enabled'] ?? true
            ), $mergedConfig),
        ], $mergedConfig['replace_placeholders'] ?? false ? [new \Monolog\Processor\PsrLogMessageProcessor()] : []);
    }

    /**
     * Parse the string level into a Monolog constant.
     *
     * @param  array  $config
     * @return int
     */
    protected function level(array $config): int
    {
        $level = $config['level'] ?? 'debug';

        $levels = [
            'debug'     => \Monolog\Logger::DEBUG,
            'info'      => \Monolog\Logger::INFO,
            'notice'    => \Monolog\Logger::NOTICE,
            'warning'   => \Monolog\Logger::WARNING,
            'error'     => \Monolog\Logger::ERROR,
            'critical'  => \Monolog\Logger::CRITICAL,
            'alert'     => \Monolog\Logger::ALERT,
            'emergency' => \Monolog\Logger::EMERGENCY,
        ];

        if (isset($levels[$level])) {
            return $levels[$level];
        }

        throw new \InvalidArgumentException('Invalid log level.');
    }

    /**
     * Parse the channel name from the configuration.
     *
     * @param  array  $config
     * @return string
     */
    protected function parseChannel(array $config): string
    {
        return $config['name'] ?? 'notifyfree';
    }

    /**
     * Prepare the handler for usage by Monolog.
     *
     * @param  \NotifyFree\LaravelLogChannel\Handlers\NotifyFreeHandler  $handler
     * @param  array  $config
     * @return \NotifyFree\LaravelLogChannel\Handlers\NotifyFreeHandler
     */
    protected function prepareHandler(NotifyFreeHandler $handler, array $config = []): NotifyFreeHandler
    {
        if (isset($config['formatter'])) {
            $handler->setFormatter($this->app->make($config['formatter'], $config['formatter_with'] ?? []));
        }

        return $handler;
    }

    /**
     * 创建NotifyFree日志记录器 (保留用于向后兼容)
     */
    protected function createNotifyFreeLogger(array $config): Logger
    {
        // 合并配置
        $notifyFreeConfig = $this->app['config']['notifyfree'] ?? [];
        $mergedConfig = array_merge($this->getDefaultConfig(), $notifyFreeConfig, $config);

        // 验证配置
        if (!$this->validateConfig($mergedConfig)) {
            return new Logger('notifyfree-fallback', [new \Monolog\Handler\NullHandler()]);
        }

        // 使用新的 createDriver 方法
        return \NotifyFree\LaravelLogChannel\NotifyFreeLogger::createDriver($mergedConfig);
    }

    /**
     * 创建处理器
     */
    protected function createHandler(array $config): NotifyFreeHandler
    {
        $handlerClass = $config['handler'] ?? NotifyFreeHandler::class;

        if (!class_exists($handlerClass)) {
            throw new \InvalidArgumentException("Handler class {$handlerClass} does not exist");
        }

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
    protected function validateConfig(array $config): bool
    {
        $required = ['endpoint', 'token', 'app_id'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                return false;
            }
        }

        // 验证URL格式
        if (!empty($config['endpoint']) && !$this->isValidUrl($config['endpoint'])) {
            return false;
        }

        return true;
    }

    /**
     * 验证URL是否有效
     */
    protected function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false ||
               preg_match('/^https?:\/\/(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+)/', $url);
    }
}
