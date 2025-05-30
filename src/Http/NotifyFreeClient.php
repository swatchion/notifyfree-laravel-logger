<?php

namespace NotifyFree\LaravelLogChannel\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use NotifyFree\LaravelLogChannel\Exceptions\NotifyFreeNetworkException;
use NotifyFree\LaravelLogChannel\Exceptions\NotifyFreeAuthException;

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

        // 如果关键配置缺失，记录警告但不抛出异常
        if (empty($this->endpoint) || empty($this->token) || empty($this->applicationId)) {
            error_log('NotifyFreeClient: Missing required configuration. Client will be non-functional.');
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
                    'Authorization' => 'Bearer ' . $this->token,
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
     * 发送单条日志数据
     */
    public function send(array $logData): bool
    {
        $attempts = 0;

        while ($attempts < $this->retryAttempts) {
            try {
                // 直接发送格式化后的日志数据，不添加额外的 app_id
                // 因为服务端会通过 token 获取关联的应用ID
                $response = $this->getHttpClient()->post($this->endpoint, [
                    'json' => $logData,
                ]);

                if ($response->getStatusCode() === 200) {
                    return true;
                }

            } catch (RequestException $e) {
                $attempts++;

                if ($e->getCode() === 401) {
                    throw new NotifyFreeAuthException('Authentication failed: Invalid token');
                }

                if ($attempts >= $this->retryAttempts) {
                    throw new NotifyFreeNetworkException(
                        'Failed to send log after ' . $this->retryAttempts . ' attempts: ' . $e->getMessage()
                    );
                }

                // 指数退避重试
                sleep(pow(2, $attempts - 1));
            }
        }

        return false;
    }

    /**
     * 批量发送日志数据
     * 注意：当前服务端可能不支持批量接口，这个方法为将来扩展预留
     */
    public function sendBatch(array $logDataBatch): bool
    {
        $attempts = 0;

        while ($attempts < $this->retryAttempts) {
            try {
                // 批量发送的数据格式
                $payload = [
                    'logs' => $logDataBatch,
                ];

                $response = $this->getHttpClient()->post($this->endpoint . '/batch', [
                    'json' => $payload,
                ]);

                if ($response->getStatusCode() === 200) {
                    return true;
                }

            } catch (RequestException $e) {
                $attempts++;

                if ($e->getCode() === 401) {
                    throw new NotifyFreeAuthException('Authentication failed: Invalid token');
                }

                // 如果批量接口不存在 (404)，尝试逐个发送
                if ($e->getCode() === 404) {
                    return $this->sendBatchIndividually($logDataBatch);
                }

                if ($attempts >= $this->retryAttempts) {
                    throw new NotifyFreeNetworkException(
                        'Failed to send batch logs after ' . $this->retryAttempts . ' attempts: ' . $e->getMessage()
                    );
                }

                // 指数退避重试
                sleep(pow(2, $attempts - 1));
            }
        }

        return false;
    }

    /**
     * 当批量接口不可用时，逐个发送日志
     */
    protected function sendBatchIndividually(array $logDataBatch): bool
    {
        $successCount = 0;
        foreach ($logDataBatch as $logData) {
            try {
                if ($this->send($logData)) {
                    $successCount++;
                }
            } catch (\Exception $e) {
                // 继续发送其他日志，不因单条失败而停止
                continue;
            }
        }

        // 如果超过一半成功，认为批量发送成功
        return $successCount > (count($logDataBatch) / 2);
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
