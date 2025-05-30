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
        // 构建符合服务端API规范的数据格式
        $formatted = [
            'message' => $this->truncateMessage($record->message),
            'level' => strtolower($record->level->getName()), // 转换为字符串，符合服务端验证规则
        ];

        // 合并 context 和 extra 作为 metadata
        $metadata = [];
        
        if ($this->shouldIncludeContext() && !empty($record->context)) {
            $metadata['context'] = $this->sanitizeData($record->context);
        }
        
        if ($this->shouldIncludeExtra() && !empty($record->extra)) {
            $metadata['extra'] = $this->sanitizeData($record->extra);
        }
        
        // 添加日志通道和时间戳信息到 metadata
        $metadata['channel'] = $record->channel;
        $metadata['timestamp'] = $record->datetime->format($this->getTimestampFormat());
        $metadata['original_level_value'] = $record->level->value;
        
        if (!empty($metadata)) {
            $formatted['metadata'] = $metadata;
        }

        // 处理 tags - 从 context 中提取或设置为空数组
        $tags = [];
        if (isset($record->context['tags']) && is_array($record->context['tags'])) {
            $tags = $record->context['tags'];
            // 从 metadata 中移除 tags，避免重复
            unset($formatted['metadata']['context']['tags']);
        }
        $formatted['tags'] = $tags;

        return $formatted;
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
     * 添加元数据信息 - 保留方法以保持向后兼容，但不再使用
     */
    protected function addMetadata(array $formatted): array
    {
        // 不再添加额外的元数据，因为现在直接构建符合服务端的格式
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
