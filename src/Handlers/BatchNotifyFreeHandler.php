<?php

namespace NotifyFree\LaravelLogChannel\Handlers;

use Monolog\LogRecord;
use NotifyFree\LaravelLogChannel\Exceptions\NotifyFreeException;

class BatchNotifyFreeHandler extends NotifyFreeHandler
{
    protected array $buffer = [];
    protected int $batchSize;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->batchSize = $config['batch_size'] ?? 10;
    }

    /**
     * 处理日志记录 - 添加到缓冲区
     */
    protected function write(LogRecord $record): void
    {
        $formatted = $this->formatter->format($record);
        $this->buffer[] = $formatted;

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * 批量发送缓冲区中的日志
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            $this->client->sendBatch($this->buffer);
            $this->buffer = [];
        } catch (NotifyFreeException $e) {
            // 批量发送失败时，使用本地备份
            $this->handleBatchSendFailure($e, $this->buffer);
            $this->buffer = [];
        }
    }

    /**
     * 处理批量发送失败
     */
    protected function handleBatchSendFailure(NotifyFreeException $e, array $logBatch): void
    {
        // 记录错误
        error_log(sprintf(
            'NotifyFree batch log sending failed: %s. Batch size: %d',
            $e->getMessage(),
            count($logBatch)
        ));

        // 如果启用了本地备份，保存整个批次
        if ($this->config['fallback']['enabled'] ?? false) {
            $this->saveBatchToLocalFallback($logBatch);
        }
    }

    /**
     * 批量保存到本地备份
     */
    protected function saveBatchToLocalFallback(array $logBatch): void
    {
        $fallbackPath = $this->config['fallback']['local_storage_path']
            ?? storage_path('logs/notifyfree-fallback.log');

        $batchData = [
            'timestamp' => now()->toDateTimeString(),
            'batch_size' => count($logBatch),
            'logs' => $logBatch,
        ];

        $logLine = json_encode($batchData) . PHP_EOL;
        file_put_contents($fallbackPath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * 析构函数 - 确保缓冲区被清空
     */
    public function __destruct()
    {
        $this->flush();
    }

    /**
     * 获取当前缓冲区大小
     */
    public function getBufferSize(): int
    {
        return count($this->buffer);
    }

    /**
     * 清空缓冲区而不发送
     */
    public function clearBuffer(): void
    {
        $this->buffer = [];
    }
}
