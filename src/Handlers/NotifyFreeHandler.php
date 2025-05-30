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
    protected string $endpoint;
    protected string $token;
    protected string $appId;
    protected int $timeout;
    protected int $retryAttempts;
    protected int $batchSize;
    protected bool $includeContext;
    protected bool $includeExtra;
    protected bool $fallbackEnabled;

    public function __construct(
        string $endpoint,
        string $token,
        string $appId,
        int $timeout = 30,
        int $retryAttempts = 3,
        int $batchSize = 10,
        bool $includeContext = true,
        bool $includeExtra = true,
        int $level = Logger::DEBUG,
        bool $bubble = true,
        bool $fallbackEnabled = true
    ) {
        parent::__construct($level, $bubble);

        $this->endpoint = $endpoint;
        $this->token = $token;
        $this->appId = $appId;
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
        $this->batchSize = $batchSize;
        $this->includeContext = $includeContext;
        $this->includeExtra = $includeExtra;
        $this->fallbackEnabled = $fallbackEnabled;

        // 构建配置数组，保持向后兼容
        $this->config = [
            'endpoint' => $endpoint,
            'token' => $token,
            'app_id' => $appId,
            'timeout' => $timeout,
            'retry_attempts' => $retryAttempts,
            'batch_size' => $batchSize,
            'format' => [
                'include_context' => $includeContext,
                'include_extra' => $includeExtra,
            ],
            'fallback' => [
                'enabled' => $fallbackEnabled,
            ],
        ];

        $this->formatter = new NotifyFreeFormatter($this->config);
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
            $isSwoole = false;
            if (extension_loaded('swoole') && class_exists('\Swoole\Coroutine', false)) {
                try {
                    /** @phpstan-ignore-next-line */
                    $isSwoole = \Swoole\Coroutine::getCid() > 0;
                } catch (\Throwable $t) {
                    $isSwoole = false;
                }
            }

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
                    'timestamp' => $record->datetime->format('c'),
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
