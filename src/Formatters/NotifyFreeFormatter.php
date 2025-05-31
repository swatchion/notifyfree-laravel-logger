<?php

namespace NotifyFree\LaravelLogger\Formatters;

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
            'level' => strtolower($record->level->getName()),
            'timestamp' => $record->datetime->format($this->getTimestampFormat()),
        ];

        // 处理 tags - 从 context 中提取或设置为空数组
        $tags = [];
        if (isset($record->context['tags']) && is_array($record->context['tags'])) {
            $tags = $record->context['tags'];
        }
        $formatted['tags'] = $tags;

        // 构建扁平化的 metadata，避免嵌套结构
        $metadata = [];
        
        // 添加基本信息
        $metadata['channel'] = $record->channel;
        $metadata['level_value'] = $record->level->value;
        
        // 合并 context 数据（排除 tags）
        if ($this->shouldIncludeContext() && !empty($record->context)) {
            foreach ($record->context as $key => $value) {
                if ($key !== 'tags') { // 排除 tags，因为已经单独处理
                    $metadata[$key] = $this->sanitizeValue($value);
                }
            }
        }
        
        // 合并 extra 数据
        if ($this->shouldIncludeExtra() && !empty($record->extra)) {
            foreach ($record->extra as $key => $value) {
                $metadata['extra_' . $key] = $this->sanitizeValue($value);
            }
        }
        
        // 只有在有数据时才添加 metadata
        if (!empty($metadata)) {
            $formatted['metadata'] = $metadata;
        }

        return $formatted;
    }

    /**
     * 清理单个值，确保不包含复杂结构
     */
    protected function sanitizeValue($value)
    {
        if (is_array($value)) {
            // 对于数组，转换为简单的键值对或序列化字符串
            if (count($value) <= 5 && $this->isSimpleArray($value)) {
                return $value; // 保持简单数组
            } else {
                return json_encode($value); // 复杂数组转为字符串
            }
        } elseif (is_object($value)) {
            return json_encode($value); // 对象转为字符串
        } elseif (is_string($value) && $this->containsSensitiveData($value)) {
            return '[FILTERED]';
        }
        
        return $value;
    }

    /**
     * 检查是否为简单数组（只包含标量值）
     */
    protected function isSimpleArray(array $array): bool
    {
        foreach ($array as $value) {
            if (is_array($value) || is_object($value)) {
                return false;
            }
        }
        return true;
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
