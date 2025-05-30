<?php

namespace NotifyFree\LaravelLogChannel\Handlers;

use Monolog\LogRecord;

class BatchNotifyFreeHandler extends NotifyFreeHandler
{
    protected array $buffer = [];
    protected int $batchSize;

    public function __construct(
        string $endpoint,
        string $token,
        string $appId,
        int $timeout = 30,
        int $retryAttempts = 3,
        int $batchSize = 10,
        bool $includeContext = true,
        bool $includeExtra = true,
        int $level = \Monolog\Logger::DEBUG,
        bool $bubble = true,
        bool $fallbackEnabled = true
    ) {
        parent::__construct(
            $endpoint,
            $token,
            $appId,
            $timeout,
            $retryAttempts,
            $batchSize,
            $includeContext,
            $includeExtra,
            $level,
            $bubble,
            $fallbackEnabled
        );
        $this->batchSize = $batchSize;
    }

    protected function write(LogRecord $record): void
    {
        $formatted = $this->formatter->format($record);
        $this->buffer[] = [
            'data' => $formatted,
            'original_record' => $record
        ];

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            $logData = array_column($this->buffer, 'data');
            $this->getClient()->sendBatch($logData);
            $this->buffer = [];
        } catch (\Exception $e) {
            $this->handleBatchSendFailure($e, $this->buffer);
            $this->buffer = [];
        }
    }

    protected function handleBatchSendFailure(\Exception $e, array $logBatch): void
    {
        // 避免循环依赖，只使用 error_log 记录批量发送失败
        $batchInfo = array_map(function($item) {
            $record = $item['original_record'];
            return sprintf('[%s] %s', $record->level->getName(), $record->message);
        }, array_slice($logBatch, 0, 3)); // 只记录前3条消息，避免日志过大

        @error_log(sprintf(
            'NotifyFree batch log sending failed: %s (Batch size: %d, Sample messages: %s)',
            $e->getMessage(),
            count($logBatch),
            implode('; ', $batchInfo)
        ));
    }

    public function __destruct()
    {
        $this->flush();
    }

    public function getBufferSize(): int
    {
        return count($this->buffer);
    }

    public function clearBuffer(): void
    {
        $this->buffer = [];
    }
}
