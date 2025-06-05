<?php

namespace NotifyFree\LaravelLogger;

use Monolog\Logger as Monolog;
use Monolog\Level;
use Monolog\Processor\PsrLogMessageProcessor;
use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;

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
                    '', // app_id deprecated, server gets it from token
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
     * @param  \NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler  $handler
     * @param  array  $config
     * @return \NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler
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
        'debug'     => Level::Debug->value,
        'info'      => Level::Info->value,
        'notice'    => Level::Notice->value,
        'warning'   => Level::Warning->value,
        'error'     => Level::Error->value,
        'critical'  => Level::Critical->value,
        'alert'     => Level::Alert->value,
        'emergency' => Level::Emergency->value,
    ];
}
