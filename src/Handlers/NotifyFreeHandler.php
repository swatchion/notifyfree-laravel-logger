<?php

namespace NotifyFree\LaravelLogChannel\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Formatter\FormatterInterface;
use NotifyFree\LaravelLogChannel\Http\NotifyFreeClient;
use NotifyFree\LaravelLogChannel\Formatters\NotifyFreeFormatter;
use NotifyFree\LaravelLogChannel\Exceptions\NotifyFreeException;

class NotifyFreeHandler extends AbstractProcessingHandler
{
    protected NotifyFreeClient $client;
    protected ?FormatterInterface $formatter = null;
    protected array $config;

    public function __construct(array $config)
    {
        parent::__construct($config['level'] ?? Logger::DEBUG);
        $this->config = $config;
        $this->client = new NotifyFreeClient($config);
        $this->formatter = new NotifyFreeFormatter($config);
    }

    /**
     * 处理日志记录
     */
    protected function write(LogRecord $record): void
    {
        try {
            $formatted = $this->formatter->format($record);
            $this->client->send($formatted);
        } catch (NotifyFreeException $e) {
            // 记录发送失败，避免影响主程序
            $this->handleSendFailure($e, $record);
        }
    }

    /**
     * 处理发送失败的情况
     */
    protected function handleSendFailure(NotifyFreeException $e, LogRecord $record): void
    {
        // 使用error_log避免循环日志
        error_log(sprintf(
            'NotifyFree log sending failed: %s. Original message: %s',
            $e->getMessage(),
            $record->message
        ));

        // 本地备份始终启用
        $this->saveToLocalFallback($record);
    }

    /**
     * 本地备份保存
     */
    protected function saveToLocalFallback(LogRecord $record): void
    {
        $fallbackPath = $this->config['path']
            ?? storage_path('logs/laravel.log');

        $logData = $this->formatter->format($record);
        $logLine = json_encode($logData) . PHP_EOL;

        file_put_contents($fallbackPath, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * 获取格式化器
     */
    public function getFormatter(): NotifyFreeFormatter
    {
        return $this->formatter;
    }

    /**
     * 设置格式化器
     */
    public function setFormatter($formatter): self
    {
        if ($formatter instanceof NotifyFreeFormatter) {
            $this->formatter = $formatter;
        }

        return $this;
    }
}
