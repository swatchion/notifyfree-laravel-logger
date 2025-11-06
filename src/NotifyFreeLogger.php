<?php

namespace NotifyFree\LaravelLogger;

use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\Processor\PsrLogMessageProcessor;
use NotifyFree\LaravelLogger\Handlers\NotifyFreeHandler;

class NotifyFreeLogger
{
    /**
     * Create a custom NotifyFree driver instance, similar to Laravel's createSlackDriver
     */
    public static function createDriver(array $config): Monolog
    {
        return new Monolog(
            self::parseChannel($config),
            [
                self::prepareHandler(
                    new NotifyFreeHandler(
                        $config,
                        self::level($config),
                        $config['bubble'] ?? true
                    ),
                    $config
                ),
            ],
            $config['replace_placeholders'] ?? false ? [new PsrLogMessageProcessor] : []
        );
    }

    /**
     * Parse the string level into a Monolog constant.
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
     */
    protected static function parseChannel(array $config): string
    {
        return $config['name'] ?? 'notifyfree';
    }

    /**
     * Prepare the handler for usage by Monolog.
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
        'debug' => Level::Debug->value,
        'info' => Level::Info->value,
        'notice' => Level::Notice->value,
        'warning' => Level::Warning->value,
        'error' => Level::Error->value,
        'critical' => Level::Critical->value,
        'alert' => Level::Alert->value,
        'emergency' => Level::Emergency->value,
    ];
}
