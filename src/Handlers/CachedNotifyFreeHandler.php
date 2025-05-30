<?php

namespace NotifyFree\LaravelLogChannel\Handlers;

use Monolog\LogRecord;

class CachedNotifyFreeHandler extends NotifyFreeHandler
{
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    /**
     * 处理日志记录，移除缓存逻辑，依赖 stack 通道的 single 作为 fallback
     */
    protected function write(LogRecord $record): void
    {
        try {
            $formatted = $this->formatter->format($record);
            $this->getClient()->send($formatted);
        } catch (\Exception $e) {
            // 通过 single 通道记录发送失败错误
            $this->logSendFailure($e, $record);
        }
    }

    /**
     * 检查 NotifyFree 服务连接状态
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
     * 获取服务状态信息
     */
    public function getServiceStatus(): array
    {
        $isConnected = $this->testConnection();
        
        return [
            'service_available' => $isConnected,
            'endpoint' => $this->config['endpoint'] ?? 'not configured',
            'last_check' => now()->toISOString(),
        ];
    }

    /**
     * 记录服务状态到日志
     */
    public function logServiceStatus(): void
    {
        $status = $this->getServiceStatus();
        
        try {
            $logger = app('log')->channel('single');
            $level = $status['service_available'] ? 'info' : 'warning';
            $message = $status['service_available'] 
                ? 'NotifyFree service is available' 
                : 'NotifyFree service is unavailable';
                
            $logger->$level($message, $status);
        } catch (\Exception $e) {
            error_log('Failed to log NotifyFree service status: ' . $e->getMessage());
        }
    }
}
