<?php

namespace NotifyFree\LaravelLogChannel\Formatters;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class NotifyFreeFormatter implements FormatterInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config['format'] ?? [];
    }

    /**
     * 格式化日志记录
     */
    public function format(LogRecord $record): array
    {
        $formatted = [
            'timestamp' => $record->datetime->format($this->getTimestampFormat()),
            'level' => $record->level->value,
            'level_name' => $record->level->getName(),
            'channel' => $record->channel,
            'message' => $this->truncateMessage($record->message),
        ];

        if ($this->shouldIncludeContext()) {
            $formatted['context'] = $this->sanitizeData($record->context);
        }

        if ($this->shouldIncludeExtra()) {
            $formatted['extra'] = $this->sanitizeData($record->extra);
        }

        return $this->addMetadata($formatted);
    }

    /**
     * 批量格式化
     */
    public function formatBatch(array $records): array
    {
        return array_map([$this, 'format'], $records);
    }

    /**
     * 获取时间戳格式
     */
    protected function getTimestampFormat(): string
    {
        return $this->config['timestamp_format'] ?? 'c';
    }

    /**
     * 是否包含上下文数据
     */
    protected function shouldIncludeContext(): bool
    {
        return $this->config['include_context'] ?? true;
    }

    /**
     * 是否包含额外数据
     */
    protected function shouldIncludeExtra(): bool
    {
        return $this->config['include_extra'] ?? true;
    }

    /**
     * 截断过长的消息
     */
    protected function truncateMessage(string $message): string
    {
        $maxLength = $this->config['max_message_length'] ?? 1000;

        if (strlen($message) > $maxLength) {
            return substr($message, 0, $maxLength - 3) . '...';
        }

        return $message;
    }

    /**
     * 敏感数据清理
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = $this->config['sensitive_keys'] ?? [
            'password', 'token', 'secret', 'key', 'auth',
            'api_key', 'access_token', 'refresh_token', 'authorization'
        ];

        return $this->recursiveFilter($data, $sensitiveKeys);
    }

    /**
     * 递归过滤敏感数据
     */
    protected function recursiveFilter(array $data, array $sensitiveKeys): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), array_map('strtolower', $sensitiveKeys))) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->recursiveFilter($value, $sensitiveKeys);
            } elseif (is_string($value) && $this->containsSensitiveData($value)) {
                $filtered[$key] = '[FILTERED]';
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * 检查字符串是否包含敏感数据
     */
    protected function containsSensitiveData(string $value): bool
    {
        $patterns = [
            '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/', // Bearer token
            '/[A-Za-z0-9]{32,}/', // Possible API key/token
            '/password[\s]*[:=][\s]*[^\s]+/i', // Password in string
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 添加元数据信息
     */
    protected function addMetadata(array $formatted): array
    {
        // 添加兼容性信息
        $formatted['_meta'] = [
            'monolog_version' => '3.x',
            'laravel_version' => '11.x',
            'php_version' => PHP_VERSION,
            'formatter_version' => '1.0.0',
        ];

        return $formatted;
    }

    /**
     * 获取配置信息
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 设置配置信息
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
}
