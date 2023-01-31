<?php

namespace App\Service;

use App\DTO\DownloadDescription;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class DownloadManager
{
    private const BASE_URL = 'https://embed.gog.com/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AuthenticationManager $authentication,
    ) {
    }

    public function getFilename(DownloadDescription $download, int $httpTimeout = 3): string
    {
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            self::BASE_URL . $download->url,
            [
                'auth_bearer' => (string) $this->authentication->getAuthorization(),
                'max_redirects' => 0,
                'timeout' => $httpTimeout,
            ]
        );
        $url = $response->getHeaders(false)['location'][0];
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $parts);
        $parts = explode('/', $parts['path']);

        return $parts[array_key_last($parts)];
    }

    public function download(
        DownloadDescription $download,
        callable $callback,
        ?int $startAt = null,
        int $httpTimeout = 3,
    ): ResponseStreamInterface {
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            self::BASE_URL . $download->url,
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
            ]
        );

        return $this->httpClient->stream($response);
    }
}
