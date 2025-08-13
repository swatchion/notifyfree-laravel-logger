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
     * 批量发送日志数据
     */
    public function sendBatch(array $logDataBatch): bool
    {
        $attempts = 0;

        while ($attempts < $this->retryAttempts) {
            try {
                // 批量发送的数据格式 - 添加 app_id 到请求数据中
                $payload = [
                    'app_id' => $this->applicationId,
                    'messages' => $logDataBatch,
                ];

                $response = $this->getHttpClient()->post($this->endpoint.'/batch', [
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

                if ($attempts >= $this->retryAttempts) {
                    throw new NotifyFreeNetworkException(
                        'Failed to send batch logs after '.$this->retryAttempts.' attempts: '.$e->getMessage()
                    );
                }

                // 指数退避重试
                sleep(pow(2, $attempts - 1));
            }
        }

        return false;
    }





    /**
     * 异步批量发送日志数据
     */
    public function sendBatchAsync(array $logDataBatch): PromiseInterface
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
            function ($e) {
                if ($e instanceof RequestException && $e->getCode() === 401) {
                    throw new NotifyFreeAuthException('Authentication failed: Invalid token');
                }



                throw new NotifyFreeNetworkException(
                    'Failed to send batch logs: ' . $e->getMessage()
                );
            }
        );
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
