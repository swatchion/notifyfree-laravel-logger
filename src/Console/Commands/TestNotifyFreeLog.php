<?php

namespace NotifyFree\LaravelLogChannel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestNotifyFreeLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifyfree:test-log {--channel=notifyfree : 日志通道}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试NotifyFree日志通道功能';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $channel = $this->option('channel');

        $this->info("开始测试NotifyFree日志通道: {$channel}");

        try {
            // 测试不同级别的日志
            Log::channel($channel)->debug('这是一条调试日志', [
                'user_id' => 123,
                'action' => 'test_debug',
                'timestamp' => now()->toDateTimeString(),
            ]);

            Log::channel($channel)->info('用户登录成功', [
                'user_id' => 456,
                'ip' => '192.168.1.100',
                'user_agent' => 'Test Browser',
            ]);

            Log::channel($channel)->warning('系统资源使用率较高', [
                'cpu_usage' => '85%',
                'memory_usage' => '78%',
                'disk_usage' => '65%',
            ]);

            Log::channel($channel)->error('数据库连接失败', [
                'database' => 'main',
                'error_code' => 'CONNECTION_TIMEOUT',
                'retry_count' => 3,
            ]);

            // 测试敏感数据过滤
            Log::channel($channel)->info('用户认证', [
                'username' => 'test_user',
                'password' => 'secret123', // 应该被过滤
                'token' => 'bearer_token_xyz', // 应该被过滤
                'email' => 'test@example.com',
            ]);

            $this->info("✅ 所有测试日志已发送到 {$channel} 通道");
            $this->info("📝 请检查以下位置:");
            $this->line("   - 远程NotifyFree服务 (如果配置正确且服务可用)");
            $this->info("💡 提示:");
            $this->line("   - 如果使用 stack 配置 [single, notifyfree]，本地日志会自动保存到: " . storage_path('logs/laravel.log'));
            $this->line("   - stack 配置提供了最佳的可靠性，无需额外的 fallback 机制");

        } catch (\Exception $e) {
            $this->error("❌ 测试失败: " . $e->getMessage());
            $this->error("错误详情: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
