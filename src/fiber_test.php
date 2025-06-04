<?php

echo "=== Fiber 功能测试 ===\n";

// 检查 Fiber 支持
if (!class_exists('\Fiber')) {
    echo "❌ Fiber 不可用 (需要 PHP 8.1+)\n";
    exit(1);
}

echo "✅ Fiber 支持可用\n\n";

// 测试基本 Fiber 功能
echo "1. 测试基本 Fiber 功能...\n";

try {
    $fiber = new Fiber(function(): string {
        echo "   Fiber 内部执行\n";
        return "Hello from Fiber";
    });
    
    echo "   启动 Fiber...\n";
    $fiber->start();
    
    echo "   Fiber 是否已终止: " . ($fiber->isTerminated() ? 'Yes' : 'No') . "\n";
    
    $result = $fiber->getReturn();
    echo "   Fiber 返回值: $result\n";
    
} catch (Exception $e) {
    echo "   ❌ Fiber 测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试多个 Fiber
echo "2. 测试多个 Fiber...\n";

try {
    $fibers = [];
    
    // 创建 3 个 Fiber
    for ($i = 1; $i <= 3; $i++) {
        $fiber = new Fiber(function() use ($i): array {
            echo "   Fiber $i 执行\n";
            usleep(100000); // 100ms 延迟模拟网络请求
            return ['id' => $i, 'result' => "Task $i completed"];
        });
        
        $fibers[] = $fiber;
    }
    
    echo "   启动所有 Fiber...\n";
    $start = microtime(true);
    
    // 启动所有 Fiber
    foreach ($fibers as $fiber) {
        $fiber->start();
    }
    
    // 收集结果
    $results = [];
    foreach ($fibers as $fiber) {
        $results[] = $fiber->getReturn();
    }
    
    $end = microtime(true);
    $duration = round(($end - $start) * 1000, 2);
    
    echo "   总耗时: {$duration}ms\n";
    echo "   结果:\n";
    foreach ($results as $result) {
        echo "     - " . $result['result'] . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ 多 Fiber 测试失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
