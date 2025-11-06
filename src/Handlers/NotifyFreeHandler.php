<?php

namespace NotifyFree\LaravelLogger\Handlers;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use NotifyFree\LaravelLogger\Formatters\NotifyFreeFormatter;
use NotifyFree\LaravelLogger\Http\NotifyFreeClient;

class NotifyFreeHandler extends AbstractProcessingHandler
{
    /**
     * Package version
     */
    public const VERSION = '1.2.0';

    /**
     * Maximum number of log entries to send in a single batch request
     * 每批次最多发送 50 条日志，如果 buffer 不足 50 条则按实际数量发送
     */
    private const BATCH_CHUNK_SIZE = 50;

    /**
     * Minimum buffer size to prevent too frequent requests
     */
    private const MIN_BUFFER_SIZE = 50;

    /**
     * Minimum flush timeout in seconds to prevent too frequent requests
     */
    private const MIN_FLUSH_TIMEOUT = 10;

    protected ?NotifyFreeClient $client = null;

    protected ?FormatterInterface $formatter = null;

    protected array $config;

    // Basic configuration
    protected string $endpoint;

    protected string $token;

    protected string $appId;

    protected int $timeout;

    protected int $retryAttempts;

    // Batch processing configuration

    protected int $batchBufferSize;

    protected int $batchFlushTimeout;

    protected array $buffer = [];

    protected float $lastFlushTime;


    // Legacy compatibility
    protected bool $includeContext;

    protected bool $includeExtra;

    public function __construct(array $config, int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        // Store config
        $this->config = $config;

        // Extract basic config
        $this->endpoint = $config['endpoint'] ?? '';
        $this->token = $config['token'] ?? '';
        $this->appId = $config['app_id'] ?? '';
        $this->timeout = $config['timeout'] ?? 30;
        $this->retryAttempts = $config['retry_attempts'] ?? 3;

        // Format config
        $formatConfig = $config['format'] ?? [];
        $this->includeContext = $formatConfig['include_context'] ?? true;
        $this->includeExtra = $formatConfig['include_extra'] ?? true;

        // Initialize features
        $this->initializeFeatures();
        $this->formatter = new NotifyFreeFormatter($this->config);
    }

    /**
     * Initialize batch processing features based on configuration
     */
    protected function initializeFeatures(): void
    {
        // Try to get configuration from Laravel config, fall back to defaults
        $globalConfig = $this->getGlobalConfig();

        // Batch processing configuration with minimum value enforcement
        $batchConfig = $globalConfig['batch'] ?? [];
        $bufferSize = $batchConfig['buffer_size'] ?? 50;
        $flushTimeout = $batchConfig['flush_timeout'] ?? 10;

        // Enforce minimum values
        $this->batchBufferSize = max($bufferSize, self::MIN_BUFFER_SIZE);
        $this->batchFlushTimeout = max($flushTimeout, self::MIN_FLUSH_TIMEOUT);
        $this->lastFlushTime = microtime(true);

        // Merge global config into local config
        $this->config = array_merge($this->config, $globalConfig);
    }

    /**
     * Get global configuration from Laravel config system
     */
    protected function getGlobalConfig(): array
    {
        // Try to get config from Laravel config system
        if (function_exists('config') && function_exists('app')) {
            try {
                $config = config('notifyfree', []);

                return is_array($config) ? $config : [];
            } catch (\Exception $e) {
                // Fallback if config system is not available
                return [];
            }
        }

        return [];
    }

    protected function getClient(): NotifyFreeClient
    {
        if ($this->client === null) {
            $this->client = new NotifyFreeClient($this->config);
        }

        return $this->client;
    }

    /**
     * Main write method with batch processing
     */
    protected function write(LogRecord $record): void
    {
        // Check if we should flush based on timeout (passive check)
        if ($this->shouldFlushByTimeout()) {
            $this->flush();
        }

        // Add record to buffer
        $this->addToBuffer($record);

        // Check if we should flush based on buffer size
        if ($this->shouldFlushBySize()) {
            $this->flush();
        }
    }



    /**
     * Add record to batch buffer with preserved original timestamp
     */
    protected function addToBuffer(LogRecord $record): void
    {
        $formatted = $this->formatter->format($record);

        // Preserve the original log record timestamp instead of using current time
        $originalTimestamp = $record->datetime->getTimestamp();
        $originalMicroseconds = (float) $record->datetime->format('U.u');

        $this->buffer[] = [
            'data' => $formatted,
            'original_record' => $record,
            'original_timestamp' => $originalTimestamp,
            'original_microseconds' => $originalMicroseconds,
            'buffer_timestamp' => microtime(true), // When it was added to buffer
        ];
    }

    /**
     * Check if we should flush based on timeout (passive check)
     */
    protected function shouldFlushByTimeout(): bool
    {
        return ! empty($this->buffer) &&
               (microtime(true) - $this->lastFlushTime) >= $this->batchFlushTimeout;
    }

    /**
     * Check if we should flush based on buffer size
     */
    protected function shouldFlushBySize(): bool
    {
        return count($this->buffer) >= $this->batchBufferSize;
    }

    /**
     * Flush all buffered records with chunk processing
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            $this->flushBufferInChunks();

            // Clear buffer and update timestamp on successful send
            $this->buffer = [];
            $this->lastFlushTime = microtime(true);

        } catch (\Exception $e) {
            $this->handleBatchSendFailure($e, $this->buffer);

            // Clear buffer even on failure (no retry for batch failures)
            $this->buffer = [];
            $this->lastFlushTime = microtime(true);
        }
    }

    /**
     * Flush buffer in chunks with concurrent processing using Guzzle Promise
     *
     * 优化后的实现：将并发处理逻辑委托给 Client，Handler 只负责协调
     */
    protected function flushBufferInChunks(): void
    {
        $logData = array_column($this->buffer, 'data');
        $chunks = array_chunk($logData, self::BATCH_CHUNK_SIZE);

        // 委托给 Client 处理并发发送
        $result = $this->getClient()->sendMultipleBatchesAsync($chunks);

        // 记录错误（如果有）
        if ($result['failed'] > 0) {
            $this->logChunkFailures($result['errors'], $result['total'], $result['success']);
        }
    }



    /**
     * Log chunk processing failures
     */
    protected function logChunkFailures(array $errors, int $totalChunks, int $successfulChunks): void
    {
        @error_log(sprintf(
            'NotifyFree batch chunk processing completed with errors: %d/%d chunks successful. Errors: %s',
            $successfulChunks,
            $totalChunks,
            implode('; ', $errors)
        ));
    }

    /**
     * Handle batch send failure by logging error
     */
    protected function handleBatchSendFailure(\Exception $e, array $logBatch): void
    {
        // Create a summary of failed batch for error logging
        $batchInfo = array_map(function ($item) {
            $record = $item['original_record'];

            return sprintf('[%s] %s', $record->level->getName(), $record->message);
        }, array_slice($logBatch, 0, 3)); // Only log first 3 messages to avoid huge logs

        @error_log(sprintf(
            'NotifyFree batch log sending failed: %s (Batch size: %d, Sample messages: %s)',
            $e->getMessage(),
            count($logBatch),
            implode('; ', $batchInfo)
        ));
    }

    /**
     * Log single send failure
     */
    protected function logSendFailure(\Exception $e, LogRecord $record): void
    {
        @error_log(sprintf(
            'NotifyFree log sending failed: %s (Original: [%s] %s)',
            $e->getMessage(),
            $record->level->getName(),
            $record->message
        ));
    }

    /**
     * Test connection to NotifyFree service
     */
    public function testConnection(): bool
    {
        try {
            return $this->getClient()->testConnection();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service status information
     */
    public function getServiceStatus(): array
    {
        $isConnected = $this->testConnection();

        return [
            'version' => self::VERSION,
            'service_available' => $isConnected,
            'endpoint' => $this->config['endpoint'] ?? 'not configured',
            'last_check' => date('c'),
            'batch_buffer_size' => $this->batchBufferSize,
            'batch_flush_timeout' => $this->batchFlushTimeout,
            'batch_chunk_size' => self::BATCH_CHUNK_SIZE,
            'current_buffer_size' => count($this->buffer),
            'concurrent_processing' => 'enabled (Guzzle Promise)',
        ];
    }

    /**
     * Log service status
     */
    public function logServiceStatus(): void
    {
        $status = $this->getServiceStatus();

        $level = $status['service_available'] ? 'INFO' : 'WARNING';
        $message = $status['service_available']
            ? 'NotifyFree service is available'
            : 'NotifyFree service is unavailable';

        @error_log(sprintf(
            'NotifyFree [%s]: %s (Endpoint: %s, Buffer: %d/%d, Chunk: %d, Concurrent: %s)',
            $level,
            $message,
            $status['endpoint'],
            $status['current_buffer_size'],
            $status['batch_buffer_size'],
            $status['batch_chunk_size'],
            $status['concurrent_processing']
        ));
    }

    /**
     * Batch processing utility methods
     */
    public function getBufferSize(): int
    {
        return count($this->buffer);
    }

    public function clearBuffer(): void
    {
        $this->buffer = [];
        $this->lastFlushTime = microtime(true);
    }



    public function setBatchBufferSize(int $size): self
    {
        $this->batchBufferSize = max($size, self::MIN_BUFFER_SIZE);

        return $this;
    }

    public function setBatchFlushTimeout(int $timeout): self
    {
        $this->batchFlushTimeout = max($timeout, self::MIN_FLUSH_TIMEOUT);

        return $this;
    }


    /**
     * Legacy compatibility methods
     */
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

    /**
     * Ensure buffer is flushed when object is destroyed
     */
    public function __destruct()
    {
        // In destructor context, use sequential processing to avoid issues
        $this->flushSafely();
    }

    /**
     * Safe flush that works in any context including destructors
     */
    protected function flushSafely(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            // Use normal flush processing in destructor context
            $this->flushBufferInChunks();

            // Clear buffer and update timestamp
            $this->buffer = [];
            $this->lastFlushTime = microtime(true);

        } catch (\Exception $e) {
            // In destructor, just log the error and continue
            @error_log(sprintf(
                'NotifyFree safe flush failed in destructor: %s',
                $e->getMessage()
            ));

            // Clear buffer to prevent memory leaks
            $this->buffer = [];
            $this->lastFlushTime = microtime(true);
        }
    }
}
