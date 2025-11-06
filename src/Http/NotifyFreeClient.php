<?php

namespace NotifyFree\LaravelLogger\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;

use NotifyFree\LaravelLogger\Exceptions\NotifyFreeAuthException;
use NotifyFree\LaravelLogger\Exceptions\NotifyFreeNetworkException;

class NotifyFreeClient
{
    protected ?Client $httpClient = null;

    protected string $endpoint;

    protected string $token;

    protected string $applicationId;

    protected int $timeout;

    protected int $retryAttempts;

    public function __construct(array $config)
    {
        $this->endpoint = $config['endpoint'] ?? '';
        $this->token = $config['token'] ?? '';
        $this->applicationId = $config['app_id'] ?? '';
        $this->timeout = $config['timeout'] ?? 30;
        $this->retryAttempts = $config['retry_attempts'] ?? 3;

        // 验证必需的配置项
        if (empty($this->endpoint)) {
            throw new \InvalidArgumentException('NotifyFree endpoint is required');
        }

        if (empty($this->token)) {
            throw new \InvalidArgumentException('NotifyFree token is required');
        }

        if (empty($this->applicationId)) {
            throw new \InvalidArgumentException('NotifyFree app_id is required. Please set NOTIFYFREE_APP_ID in your .env file');
        }
    }

    /**
     * 延迟初始化HTTP客户端
     */
    protected function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            // 在 Swoole 环境中需要特殊的配置
            $options = [
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$this->token,
                    'User-Agent' => 'NotifyFree-Laravel-Log-Channel/1.0',
                ],
            ];

            // 检查是否在 Swoole 环境中
            if (extension_loaded('swoole')) {
                // Swoole 环境下的特殊配置
                $options['curl'] = [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // 强制使用 IPv4
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ];
            }

            $this->httpClient = new Client($options);
        }

        return $this->httpClient;
    }







    /**
     * 异步批量发送日志数据（带重试机制）
     */
    public function sendBatchAsync(array $logDataBatch, int $attempt = 0): PromiseInterface
    {
        // 批量发送的数据格式 - 添加 app_id 到请求数据中
        $payload = [
            'app_id' => $this->applicationId,
            'messages' => $logDataBatch,
        ];

        return $this->getHttpClient()->postAsync($this->endpoint . '/batch', [
            'json' => $payload,
        ])->then(
            function ($response) {
                return $response->getStatusCode() === 200;
            },
            function ($e) use ($logDataBatch, $attempt) {
                if ($e instanceof RequestException && $e->getCode() === 401) {
                    throw new NotifyFreeAuthException('Authentication failed: Invalid token');
                }

                // 如果还有重试次数，进行重试
                if ($attempt < $this->retryAttempts - 1) {
                    // 指数退避等待
                    usleep(pow(2, $attempt) * 1000000);
                    return $this->sendBatchAsync($logDataBatch, $attempt + 1);
                }

                throw new NotifyFreeNetworkException(
                    'Failed to send batch logs after ' . $this->retryAttempts . ' attempts: ' . $e->getMessage()
                );
            }
        );
    }

    /**
     * 并发发送多个批次（优化后的实现）
     *
     * @param array $batches 多个批次的日志数据
     * @return array 返回结果统计 ['success' => int, 'failed' => int, 'errors' => array]
     */
    public function sendMultipleBatchesAsync(array $batches): array
    {
        if (empty($batches)) {
            return ['success' => 0, 'failed' => 0, 'errors' => []];
        }

        $totalBatches = count($batches);
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        try {
            // 创建所有异步请求的 promises
            $promises = [];
            foreach ($batches as $index => $batch) {
                $promises[$index] = $this->sendBatchAsync($batch);
            }

            // 使用 Guzzle Promise 并发执行所有请求
            $results = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

            // 处理结果
            foreach ($results as $index => $result) {
                if ($result['state'] === 'fulfilled' && $result['value'] === true) {
                    $successCount++;
                } else {
                    $failedCount++;
                    $errorMsg = $result['state'] === 'rejected'
                        ? ($result['reason']->getMessage() ?? 'Unknown error')
                        : 'Request failed';
                    $errors[] = sprintf('Batch %d/%d: %s', $index + 1, $totalBatches, $errorMsg);
                }
            }

        } catch (\Exception $e) {
            // Promise 处理本身失败
            $failedCount = $totalBatches;
            $errors[] = 'Promise processing failed: ' . $e->getMessage();
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'total' => $totalBatches,
            'errors' => $errors,
        ];
    }



    /**
     * 测试连接
     */
    public function testConnection(): bool
    {
        try {
            // 使用 HEAD 请求测试连接，避免实际发送数据
            $response = $this->getHttpClient()->head($this->endpoint);

            return in_array($response->getStatusCode(), [200, 405]); // 405 表示方法不允许但端点存在
        } catch (\Exception $e) {
            return false;
        }
    }
}
