<?php

namespace NotifyFree\LaravelLogChannel\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Formatter\FormatterInterface;
use NotifyFree\LaravelLogChannel\Http\NotifyFreeClient;
use NotifyFree\LaravelLogChannel\Formatters\NotifyFreeFormatter;

class NotifyFreeHandler extends AbstractProcessingHandler
{
    protected ?NotifyFreeClient $client = null;
    protected ?FormatterInterface $formatter = null;
    protected array $config;

    public function __construct(array $config)
    {
        parent::__construct($config['level'] ?? Logger::DEBUG);
        $this->config = $config;
        $this->formatter = new NotifyFreeFormatter($config);
    }

    protected function getClient(): NotifyFreeClient
    {
        if ($this->client === null) {
            $this->client = new NotifyFreeClient($this->config);
        }
        return $this->client;
    }

    protected function write(LogRecord $record): void
    {
        try {
            $formatted = $this->formatter->format($record);
            $this->getClient()->send($formatted);
        } catch (\Exception $e) {
            // 通过 Laravel 日志系统记录发送失败，避免循环引用
            $this->logSendFailure($e, $record);
        }
    }

    protected function logSendFailure(\Exception $e, LogRecord $record): void
    {
        // 在容器环境下，优先使用 Laravel 日志系统，失败时使用 @error_log 压制错误
        try {
            // 检查是否在 Swoole 环境中
            $isSwoole = extension_loaded('swoole') && class_exists('\Swoole\Coroutine') && \Swoole\Coroutine::getCid() > 0;
            
            if ($isSwoole) {
                // 在协程环境中，直接使用 @error_log，压制任何错误
                @error_log(sprintf(
                    'NotifyFree log sending failed in Swoole context: %s (Original: %s)',
                    $e->getMessage(),
                    $record->message
                ));
            } else {
                // 非 Swoole 环境，正常使用 Laravel 日志
                $logger = app('log')->channel('single');
                $logger->warning('NotifyFree log sending failed', [
                    'error' => $e->getMessage(),
                    'original_message' => $record->message,
                    'original_level' => $record->level->getName(),
                    'original_channel' => $record->channel,
                    'timestamp' => $record->datetime->toISOString(),
                ]);
            }
        } catch (\Exception $logError) {
            // 容器环境下的最后容错措施：使用 @ 压制 error_log 的任何错误
            @error_log(sprintf(
                'NotifyFree: Failed to log error. Original error: %s, Log error: %s',
                $e->getMessage(),
                $logError->getMessage()
            ));
        }
    }

    public function getFormatter(): NotifyFreeFormatter
    {
        return $this->formatter;
    }

    public function setFormatter($formatter): self
    {
        if ($formatter instanceof NotifyFreeFormatter) {
            $this->formatter = $formatter;
        }
        return $this;
    }
}
