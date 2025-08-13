<?php

namespace NotifyFree\LaravelLogger\Console\Commands;

use Illuminate\Console\Command;
use NotifyFree\LaravelLogger\Handlers\CachedNotifyFreeHandler;

class NotifyFreeCacheManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifyfree:cache {action : 管理动作：stats|retry|clear}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '管理NotifyFree缓存日志（查看统计、重试发送、清空缓存）';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        $handler = $this->createCachedHandler();

        switch ($action) {
            case 'stats':
                $this->showCacheStats($handler);
                break;

            case 'retry':
                $this->retryCachedLogs($handler);
                break;

            case 'clear':
                $this->clearCache($handler);
                break;

            default:
                $this->error("未知的操作: {$action}");
                $this->info('可用操作: stats, retry, clear');

                return 1;
        }

        return 0;
    }

    /**
     * 创建缓存处理器
     */
    protected function createCachedHandler(): CachedNotifyFreeHandler
    {
        $config = config('notifyfree');

        return new CachedNotifyFreeHandler($config);
    }

    /**
     * 显示缓存统计信息
     */
    protected function showCacheStats(CachedNotifyFreeHandler $handler): void
    {
        $stats = $handler->getCacheStats();

        $this->info('📊 NotifyFree缓存日志统计');
        $this->line('========================');

        if (! $stats['file_exists']) {
            $this->line('✅ 没有缓存文件，所有日志都已成功发送');

            return;
        }

        $this->line("📄 缓存文件: {$stats['file_path']}");
        $this->line('📏 文件大小: '.$this->formatBytes($stats['file_size']));
        $this->line("📝 日志条数: {$stats['log_count']}");
        $this->line('🗂️ 最大文件大小: '.$this->formatBytes($stats['max_file_size']));

        if ($stats['log_count'] > 0) {
            $this->warn("⚠️  有 {$stats['log_count']} 条日志等待重试发送");
            $this->line("💡 使用 'php artisan notifyfree:cache retry' 重试发送");
        }
    }

    /**
     * 重试发送缓存的日志
     */
    protected function retryCachedLogs(CachedNotifyFreeHandler $handler): void
    {
        $this->info('🔄 开始重试发送缓存日志...');

        $successCount = $handler->retryCachedLogs();

        if ($successCount > 0) {
            $this->info("✅ 成功重试发送 {$successCount} 条日志");
        } else {
            $this->warn('⚠️  没有日志可以重试，或重试全部失败');
        }

        // 显示重试后的统计信息
        $this->line('');
        $this->showCacheStats($handler);
    }

    /**
     * 清空缓存
     */
    protected function clearCache(CachedNotifyFreeHandler $handler): void
    {
        if (! $this->confirm('确定要清空所有缓存日志吗？此操作不可逆！')) {
            $this->info('取消操作');

            return;
        }

        $stats = $handler->getCacheStats();

        if (! $stats['file_exists']) {
            $this->info('ℹ️  没有缓存文件需要清空');

            return;
        }

        $logCount = $stats['log_count'];

        if ($handler->clearCache()) {
            $this->info("🗑️  已清空 {$logCount} 条缓存日志");
        } else {
            $this->error('❌ 清空缓存失败');
        }
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
