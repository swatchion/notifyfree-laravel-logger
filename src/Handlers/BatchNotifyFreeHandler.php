<?php

namespace NotifyFree\LaravelLogChannel\Handlers;

use Monolog\LogRecord;

class BatchNotifyFreeHandler extends NotifyFreeHandler
{
    protected array $buffer = [];
    protected int $batchSize;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->batchSize = $config['batch_size'] ?? 10;
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
        try {
            $logger = app('log')->channel('single');
            $logger->warning('NotifyFree batch log sending failed', [
                'error' => $e->getMessage(),
                'batch_size' => count($logBatch),
                'batch_messages' => array_map(function($item) {
                    $record = $item['original_record'];
                    return [
                        'message' => $record->message,
                        'level' => $record->level->getName(),
                        'channel' => $record->channel,
                    ];
                }, array_slice($logBatch, 0, 5)), // 只记录前5条消息，避免日志过大
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $logError) {
            // 容器环境下使用 @ 压制 error_log 的任何错误
            @error_log(sprintf(
                'NotifyFree: Failed to log batch error via single channel. Original error: %s, Log error: %s',
                $e->getMessage(),
                $logError->getMessage()
            ));
        }
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
