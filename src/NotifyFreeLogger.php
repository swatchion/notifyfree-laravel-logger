<?php

namespace NotifyFree\LaravelLogChannel;

use Monolog\Logger as Monolog;
use Monolog\Processor\PsrLogMessageProcessor;
use NotifyFree\LaravelLogChannel\Handlers\NotifyFreeHandler;

class NotifyFreeLogger
{
    /**
     * Create a custom NotifyFree driver instance, similar to Laravel's createSlackDriver
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public static function createDriver(array $config): Monolog
    {
        return new Monolog(
            self::parseChannel($config),
            [
                self::prepareHandler(new NotifyFreeHandler(
                    $config['endpoint'] ?? '',
                    $config['token'] ?? '',
                    $config['app_id'] ?? '',
                    $config['timeout'] ?? 30,
                    $config['retry_attempts'] ?? 3,
                    $config['batch_size'] ?? 10,
                    $config['include_context'] ?? true,
                    $config['include_extra'] ?? true,
                    self::level($config),
                    $config['bubble'] ?? true,
                    $config['fallback_enabled'] ?? true
                ), $config),
            ],
            $config['replace_placeholders'] ?? false ? [new PsrLogMessageProcessor()] : []
        );
    }

    /**
     * Parse the string level into a Monolog constant.
     *
     * @param  array  $config
     * @return int
     */
    protected static function level(array $config): int
    {
        $level = $config['level'] ?? 'debug';

        if (isset(self::$levels[$level])) {
            return self::$levels[$level];
        }

        throw new \InvalidArgumentException('Invalid log level.');
    }

    /**
     * Parse the channel name from the configuration.
     *
     * @param  array  $config
     * @return string
     */
    protected static function parseChannel(array $config): string
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
    protected static function prepareHandler(NotifyFreeHandler $handler, array $config = []): NotifyFreeHandler
    {
        if (isset($config['formatter']) && function_exists('app')) {
            $handler->setFormatter(app()->make($config['formatter'], $config['formatter_with'] ?? []));
        }

        return $handler;
    }

    /**
     * The array of log levels.
     *
     * @var array
     */
    protected static $levels = [
        'debug'     => Monolog::DEBUG,
        'info'      => Monolog::INFO,
        'notice'    => Monolog::NOTICE,
        'warning'   => Monolog::WARNING,
        'error'     => Monolog::ERROR,
        'critical'  => Monolog::CRITICAL,
        'alert'     => Monolog::ALERT,
        'emergency' => Monolog::EMERGENCY,
    ];
}
