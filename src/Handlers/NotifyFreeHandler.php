<?php

namespace NotifyFree\LaravelLogger\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Formatter\FormatterInterface;
use NotifyFree\LaravelLogger\Http\NotifyFreeClient;
use NotifyFree\LaravelLogger\Formatters\NotifyFreeFormatter;

class NotifyFreeHandler extends AbstractProcessingHandler
{
    /**
     * Maximum number of log entries to send in a single batch request
     */
    private const BATCH_CHUNK_SIZE = 10;

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
    protected bool $batchEnabled;
    protected int $batchBufferSize;
    protected int $batchFlushTimeout;
    protected array $buffer = [];
    protected float $lastFlushTime;

    // Cache configuration
    protected bool $cacheServiceStatusEnabled;
    protected int $cacheServiceStatusTtl;
    protected ?bool $serviceStatusCache = null;
    protected float $lastServiceStatusCheck = 0.0;

    // Legacy compatibility
    protected bool $includeContext;
    protected bool $includeExtra;
    protected bool $fallbackEnabled;

    public function __construct(
        string $endpoint,
        string $token,
        string $appId,
        int $timeout = 30,
        int $retryAttempts = 3,
        int $batchSize = 10, // Deprecated parameter for backward compatibility
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
        $this->includeContext = $includeContext;
        $this->includeExtra = $includeExtra;
        $this->fallbackEnabled = $fallbackEnabled;

        // Build configuration array for backward compatibility
        $this->config = [
            'endpoint' => $endpoint,
            'token' => $token,
            'app_id' => $appId,
            'timeout' => $timeout,
            'retry_attempts' => $retryAttempts,
            'batch_size' => $batchSize, // Deprecated
            'format' => [
                'include_context' => $includeContext,
                'include_extra' => $includeExtra,
            ],
            'fallback' => [
                'enabled' => $fallbackEnabled,
            ],
        ];

        $this->initializeFeatures();
        $this->formatter = new NotifyFreeFormatter($this->config);
    }

    /**
     * Initialize batch processing and cache features based on configuration
     */
    protected function initializeFeatures(): void
    {
        // Try to get configuration from Laravel config, fall back to defaults
        $globalConfig = $this->getGlobalConfig();

        // Batch processing configuration
        $batchConfig = $globalConfig['batch'] ?? [];
        $this->batchEnabled = $batchConfig['enabled'] ?? true;
        $this->batchBufferSize = $batchConfig['buffer_size'] ?? $this->config['batch_size'] ?? 50;
        $this->batchFlushTimeout = $batchConfig['flush_timeout'] ?? 5;
        $this->lastFlushTime = microtime(true);

        // Cache configuration
        $cacheConfig = $globalConfig['cache'] ?? [];
        $this->cacheServiceStatusEnabled = $cacheConfig['service_status_enabled'] ?? true;
        $this->cacheServiceStatusTtl = $cacheConfig['service_status_ttl'] ?? 60;

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
     * Main write method with integrated batch processing and caching
     */
    protected function write(LogRecord $record): void
    {
        if (!$this->batchEnabled) {
            $this->writeSingle($record);
            return;
        }

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
     * Write a single record directly (non-batch mode)
     */
    protected function writeSingle(LogRecord $record): void
    {
        try {
            $formatted = $this->formatter->format($record);
            $this->getClient()->send($formatted);
        } catch (\Exception $e) {
            $this->logSendFailure($e, $record);
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
            'buffer_timestamp' => microtime(true) // When it was added to buffer
        ];
    }

    /**
     * Check if we should flush based on timeout (passive check)
     */
    protected function shouldFlushByTimeout(): bool
    {
        return !empty($this->buffer) &&
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
     * Flush buffer in chunks with context-aware processing
     */
    protected function flushBufferInChunks(): void
    {
        $logData = array_column($this->buffer, 'data');
        $chunks = array_chunk($logData, self::BATCH_CHUNK_SIZE);

        $totalChunks = count($chunks);

        // Check if Fiber is available and safe to use in current context
        if (class_exists('\Fiber') && $this->canUseFiber()) {
            $this->flushChunksConcurrently($chunks);
        } else {
            // Fallback to sequential processing
            $this->flushChunksSequentially($chunks);
        }
    }

    /**
     * Check if Fiber can be safely used in current execution context
     */
    protected function canUseFiber(): bool
    {
        // Don't use Fiber in destructor context or during shutdown
        if ($this->isInDestructorContext()) {
            return false;
        }

        // Try to create a simple test Fiber to check if context allows it
        try {
            $testFiber = new \Fiber(function() {
                return true;
            });
            // Don't start it, just check if creation is allowed
            unset($testFiber);
            return true;
        } catch (\FiberError $e) {
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if we're in a destructor context (PHP shutdown)
     */
    protected function isInDestructorContext(): bool
    {
        // Check if we're in shutdown phase
        if (function_exists('connection_status')) {
            $status = connection_status();
            if ($status === CONNECTION_ABORTED || $status === CONNECTION_TIMEOUT) {
                return true;
            }
        }

        // Check if script is ending
        if (function_exists('fastcgi_finish_request')) {
            // We're likely in a web context, check if finishing
            static $finishing = false;
            if ($finishing) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flush chunks using Fiber-based concurrent processing with safety checks
     */
    protected function flushChunksConcurrently(array $chunks): void
    {
        $totalChunks = count($chunks);
        $successfulChunks = 0;
        $errors = [];
        $client = $this->getClient();

        // Create all Fibers for concurrent execution
        $fibers = [];
        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $fibers[$chunkIndex] = new \Fiber(function() use ($client, $chunk, $chunkIndex) {
                    try {
                        $result = $client->sendBatch($chunk);
                        return [
                            'success' => $result,
                            'chunk_index' => $chunkIndex
                        ];
                    } catch (\Exception $e) {
                        return [
                            'success' => false,
                            'chunk_index' => $chunkIndex,
                            'error' => $e->getMessage()
                        ];
                    }
                });
            } catch (\FiberError $e) {
                // If Fiber creation fails, fall back to sequential processing
                $this->flushChunksSequentially($chunks);
                return;
            }
        }

        // Execute all Fibers and collect results
        foreach ($fibers as $chunkIndex => $fiber) {
            try {
                $fiber->start();
                $result = $fiber->getReturn();

                if ($result['success']) {
                    $successfulChunks++;
                } else {
                    $errors[] = sprintf(
                        'Chunk %d/%d failed: %s',
                        $result['chunk_index'] + 1,
                        $totalChunks,
                        $result['error'] ?? 'Unknown error'
                    );
                }
            } catch (\FiberError $e) {
                // If Fiber execution fails, try to continue with remaining chunks
                $errors[] = sprintf(
                    'Fiber %d/%d execution failed (FiberError): %s',
                    $chunkIndex + 1,
                    $totalChunks,
                    $e->getMessage()
                );
            } catch (\Exception $e) {
                $errors[] = sprintf(
                    'Fiber %d/%d execution failed: %s',
                    $chunkIndex + 1,
                    $totalChunks,
                    $e->getMessage()
                );
            }
        }

        // Log any errors
        if (!empty($errors)) {
            $this->logChunkFailures($errors, $totalChunks, $successfulChunks);
        }
    }

    /**
     * Fallback: Flush chunks sequentially for PHP < 8.1
     */
    protected function flushChunksSequentially(array $chunks): void
    {
        $totalChunks = count($chunks);
        $successfulChunks = 0;
        $errors = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $this->getClient()->sendBatch($chunk);
                $successfulChunks++;
            } catch (\Exception $e) {
                $errors[] = sprintf(
                    'Chunk %d/%d failed: %s',
                    $chunkIndex + 1,
                    $totalChunks,
                    $e->getMessage()
                );
            }
        }

        // If any chunks failed, log the errors
        if (!empty($errors)) {
            $this->logChunkFailures($errors, $totalChunks, $successfulChunks);
        }
    }

    /**
     * Log chunk processing failures with concurrency information
     */
    protected function logChunkFailures(array $errors, int $totalChunks, int $successfulChunks): void
    {
        $processingMode = class_exists('\Fiber') ? 'concurrent (Fiber)' : 'sequential';

        @error_log(sprintf(
            'NotifyFree batch chunk processing (%s) completed with errors: %d/%d chunks successful. Errors: %s',
            $processingMode,
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
        $batchInfo = array_map(function($item) {
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
     * Test connection with caching support
     */
    public function testConnection(): bool
    {
        if (!$this->cacheServiceStatusEnabled) {
            return $this->performConnectionTest();
        }

        $now = microtime(true);

        // Check if we have cached result and it's still valid
        if ($this->serviceStatusCache !== null &&
            ($now - $this->lastServiceStatusCheck) < $this->cacheServiceStatusTtl) {
            return $this->serviceStatusCache;
        }

        // Perform actual connection test and cache result
        $this->serviceStatusCache = $this->performConnectionTest();
        $this->lastServiceStatusCheck = $now;

        return $this->serviceStatusCache;
    }

    /**
     * Perform actual connection test
     */
    protected function performConnectionTest(): bool
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
            'service_available' => $isConnected,
            'endpoint' => $this->config['endpoint'] ?? 'not configured',
            'last_check' => date('c'),
            'cache_enabled' => $this->cacheServiceStatusEnabled,
            'cache_ttl' => $this->cacheServiceStatusTtl,
            'batch_enabled' => $this->batchEnabled,
            'batch_buffer_size' => $this->batchBufferSize,
            'batch_flush_timeout' => $this->batchFlushTimeout,
            'batch_chunk_size' => self::BATCH_CHUNK_SIZE,
            'current_buffer_size' => count($this->buffer),
            'fiber_support' => class_exists('\Fiber'),
            'concurrent_processing' => class_exists('\Fiber') ? 'enabled' : 'disabled (PHP 8.1+ required)',
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
            'NotifyFree [%s]: %s (Endpoint: %s, Batch: %s, Cache: %s, Buffer: %d/%d, Chunk: %d, Fiber: %s)',
            $level,
            $message,
            $status['endpoint'],
            $status['batch_enabled'] ? 'enabled' : 'disabled',
            $status['cache_enabled'] ? 'enabled' : 'disabled',
            $status['current_buffer_size'],
            $status['batch_buffer_size'],
            $status['batch_chunk_size'],
            $status['fiber_support'] ? 'enabled' : 'disabled'
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

    public function setBatchEnabled(bool $enabled): self
    {
        $this->batchEnabled = $enabled;
        if (!$enabled) {
            $this->flush(); // Flush any remaining data when disabling batch
        }
        return $this;
    }

    public function setBatchBufferSize(int $size): self
    {
        $this->batchBufferSize = max(1, $size); // Ensure minimum size of 1
        return $this;
    }

    public function setBatchFlushTimeout(int $timeout): self
    {
        $this->batchFlushTimeout = max(1, $timeout); // Ensure minimum timeout of 1 second
        return $this;
    }

    /**
     * Cache utility methods
     */
    public function setCacheServiceStatusEnabled(bool $enabled): self
    {
        $this->cacheServiceStatusEnabled = $enabled;
        if (!$enabled) {
            $this->invalidateServiceStatusCache();
        }
        return $this;
    }

    public function invalidateServiceStatusCache(): void
    {
        $this->serviceStatusCache = null;
        $this->lastServiceStatusCheck = 0.0;
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
     * Ensure buffer is flushed when object is destroyed (Fiber-safe)
     */
    public function __destruct()
    {
        // In destructor context, we must avoid Fiber usage
        // Force sequential processing by temporarily disabling Fiber capability
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
            // Force sequential processing in destructor context
            $logData = array_column($this->buffer, 'data');
            $chunks = array_chunk($logData, self::BATCH_CHUNK_SIZE);

            $this->flushChunksSequentially($chunks);

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
