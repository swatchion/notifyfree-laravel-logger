<?php

namespace NotifyFree\LaravelLogChannel\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use NotifyFree\LaravelLogChannel\Exceptions\NotifyFreeNetworkException;
use NotifyFree\LaravelLogChannel\Exceptions\NotifyFreeAuthException;

class NotifyFreeClient
{
    protected Client $httpClient;
    protected string $endpoint;
    protected string $token;
    protected string $applicationId;
    protected int $timeout;
    protected int $retryAttempts;

    public function __construct(array $config)
    {
        $this->endpoint = $config['endpoint'];
        $this->token = $config['token'];
        $this->applicationId = $config['app_id'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->retryAttempts = $config['retry_attempts'] ?? 3;

        $this->httpClient = new Client([
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'NotifyFree-Laravel-Log-Channel/1.0',
            ],
        ]);
    }

    /**
     * 发送单条日志数据
     */
    public function send(array $logData): bool
    {
        $attempts = 0;

        while ($attempts < $this->retryAttempts) {
            try {
                $response = $this->httpClient->post($this->endpoint, [
                    'json' => array_merge($logData, [
                        'app_id' => $this->applicationId,
                    ]),
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
     */
    public function sendBatch(array $logDataBatch): bool
    {
        $attempts = 0;

        while ($attempts < $this->retryAttempts) {
            try {
                $payload = [
                    'app_id' => $this->applicationId,
                    'logs' => $logDataBatch,
                ];

                $response = $this->httpClient->post($this->endpoint . '/batch', [
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
     * 测试连接
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->httpClient->get($this->endpoint . '/health');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
