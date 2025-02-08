<?php

namespace App\Service;

use App\DTO\DownloadDescription;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class DownloadManager
{
    private const BASE_URL = 'https://embed.gog.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AuthenticationManager $authentication,
    ) {
    }

    public function getDownloadUrl(DownloadDescription $download): string
    {
        return self::BASE_URL . $download->url;
    }

    public function getFilename(DownloadDescription $download, int $httpTimeout = 3): ?string
    {
        $url = $this->getRealDownloadUrl($download, $httpTimeout);
        if (!$url) {
            return null;
        }

        return urldecode(pathinfo($url, PATHINFO_BASENAME));
    }

    public function getFileSize(DownloadDescription $download, int $httpTimeout = 3): ?int
    {
        $url = $this->getRealDownloadUrl($download, $httpTimeout);
        if (!$url) {
            return null;
        }

        $response = $this->httpClient->request(
            Request::METHOD_HEAD,
            $url,
        );
        return $response->getHeaders()['content-length'][0] ?? null;
    }

    public function download(
        DownloadDescription $download,
        callable $callback,
        ?int $startAt = null,
        int $httpTimeout = 3,
        array $curlOptions = [],
    ): ResponseStreamInterface {
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            $this->getDownloadUrl($download),
            [
                'auth_bearer' => (string) $this->authentication->getAuthorization(),
                'max_redirects' => 0,
                'timeout' => $httpTimeout,
            ]
        );
        $url = $response->getHeaders(false)['location'][0];

        $headers = [];
        if ($startAt !== null) {
            $headers['Range'] = "bytes={$startAt}-";
        }
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            $url,
            [
                'on_progress' => $callback,
                'auth_bearer' => (string) $this->authentication->getAuthorization(),
                'headers' => $headers,
                'timeout' => $httpTimeout,
                'extra' => [
                    'curl' => $curlOptions,
                ],
            ],
        );

        return $this->httpClient->stream($response);
    }

    private function getRealDownloadUrl(DownloadDescription $download, int $httpTimeout = 3): ?string
    {
        $response = $this->httpClient->request(
            Request::METHOD_HEAD,
            $this->getDownloadUrl($download),
            [
                'auth_bearer' => (string) $this->authentication->getAuthorization(),
                'max_redirects' => 0,
                'timeout' => $httpTimeout,
            ]
        );
        return $response->getHeaders(false)['location'][0] ?? null;
    }
}
