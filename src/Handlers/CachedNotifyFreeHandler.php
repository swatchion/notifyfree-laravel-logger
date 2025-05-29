<?php

namespace NotifyFree\LaravelLogChannel\Handlers;

use Monolog\LogRecord;
use NotifyFree\LaravelLogChannel\Exceptions\NotifyFreeException;

class CachedNotifyFreeHandler extends NotifyFreeHandler
{
    protected string $fallbackPath;
    protected int $maxFileSize;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->fallbackPath = $config['path'] ?? storage_path('logs/notifyfree-fallback.log');
        $this->maxFileSize = $config['max_file_size'] ?? (10 * 1024 * 1024); // 10MB 默认值
    }

    /**
     * 处理日志记录，支持本地缓存
     */
    protected function write(LogRecord $record): void
    {
        try {
            parent::write($record);
        } catch (NotifyFreeException $e) {
            // 本地缓存始终启用
            $this->cacheLocally($record);
            // 不重新抛出异常，保证不影响主程序
        }
    }

    /**
     * 本地缓存日志
     */
    protected function cacheLocally(LogRecord $record): void
    {
        $formatted = $this->formatter->format($record);

        // 添加缓存标记
        $cacheData = [
            'cached_at' => now()->toDateTimeString(),
            'original_record' => $formatted,
            'cache_reason' => 'network_failure',
        ];

        $logLine = json_encode($cacheData) . PHP_EOL;

        // 检查文件大小，必要时轮转
        $this->rotateLogFileIfNeeded();

        file_put_contents($this->fallbackPath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * 检查并轮转日志文件
     */
    protected function rotateLogFileIfNeeded(): void
    {
        if (!file_exists($this->fallbackPath)) {
            return;
        }

        if (filesize($this->fallbackPath) > $this->maxFileSize) {
            $rotatedPath = $this->fallbackPath . '.' . date('Y-m-d-H-i-s');
            rename($this->fallbackPath, $rotatedPath);

            // 记录轮转信息
            error_log("NotifyFree fallback log rotated to: {$rotatedPath}");
        }
    }

    /**
     * 重试发送缓存的日志
     */
    public function retryCachedLogs(): int
    {
        if (!file_exists($this->fallbackPath)) {
            return 0;
        }

        $lines = file($this->fallbackPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $successCount = 0;
        $failedLogs = [];

        foreach ($lines as $line) {
            try {
                $cacheData = json_decode($line, true);
                if (!$cacheData || !isset($cacheData['original_record'])) {
                    continue;
                }

                // 尝试重新发送
                $this->client->send($cacheData['original_record']);
                $successCount++;

            } catch (NotifyFreeException $e) {
                // 发送失败，保留在缓存中
                $failedLogs[] = $line;
            }
        }

        // 更新缓存文件，只保留发送失败的日志
        if ($successCount > 0) {
            $this->updateCacheFile($failedLogs);
        }

        return $successCount;
    }

    /**
     * 更新缓存文件
     */
    protected function updateCacheFile(array $failedLogs): void
    {
        if (empty($failedLogs)) {
            // 所有日志都发送成功，删除缓存文件
            unlink($this->fallbackPath);
        } else {
            // 只保留发送失败的日志
            file_put_contents($this->fallbackPath, implode(PHP_EOL, $failedLogs) . PHP_EOL);
        }
    }

    /**
     * 获取缓存日志统计
     */
    public function getCacheStats(): array
    {
        if (!file_exists($this->fallbackPath)) {
            return [
                'file_exists' => false,
                'file_size' => 0,
                'log_count' => 0,
            ];
        }

        $lines = file($this->fallbackPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return [
            'file_exists' => true,
            'file_size' => filesize($this->fallbackPath),
            'log_count' => count($lines),
            'file_path' => $this->fallbackPath,
            'max_file_size' => $this->maxFileSize,
        ];
    }

    /**
     * 清空缓存
     */
    public function clearCache(): bool
    {
        if (file_exists($this->fallbackPath)) {
            return unlink($this->fallbackPath);
        }

        return true;
    }
}
