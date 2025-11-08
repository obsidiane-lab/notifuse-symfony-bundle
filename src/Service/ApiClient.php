<?php

namespace Notifuse\SymfonyBundle\Service;

use Notifuse\SymfonyBundle\Service\Exception\NotifuseClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class ApiClient
{
    private array $options;
    private string $apiBaseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $apiBaseUrl,
        private readonly string $workspaceId,
        private readonly string $workspaceApiKey,
        array $options = []
    ) {
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->options = array_merge(
            [
                'timeout' => 10.0,
                'max_redirects' => 5,
                'verify_peer' => true,
                'headers' => [],
            ],
            $options
        );
    }

    public function request(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->buildUrl($endpoint);

        try {
            $response = $this->httpClient->request($method, $url, $this->mergeOptions($options));
            return $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new NotifuseClientException(
                sprintf('Notifuse API request failed [%s %s]: %s', strtoupper($method), $url, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    private function buildUrl(string $endpoint): string
    {
        return $this->apiBaseUrl . '/' . ltrim($endpoint, '/');
    }

    private function mergeOptions(array $options): array
    {
        $customOptions = $options;
        $customHeaders = $customOptions['headers'] ?? [];
        unset($customOptions['headers']);

        $mergedHeaders = array_merge($this->buildHeaders(), $customHeaders);

        return array_merge(
            [
                'headers' => $mergedHeaders,
                'timeout' => $this->options['timeout'],
                'max_redirects' => $this->options['max_redirects'],
                'verify_peer' => $this->options['verify_peer'],
            ],
            $customOptions
        );
    }

    private function buildHeaders(): array
    {
        return array_merge(
            [
                'Accept' => 'application/json',
                'Authorization' => sprintf('Bearer %s', $this->workspaceApiKey),
                'X-Workspace-ID' => $this->workspaceId,
            ],
            $this->options['headers'] ?? []
        );
    }
}
