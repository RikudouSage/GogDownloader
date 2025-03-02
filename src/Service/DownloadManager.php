<?php

namespace App\Service;

use App\DTO\DownloadDescription;
use App\DTO\GameExtra;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function getDownloadUrl(
        DownloadDescription|GameExtra $download
    ): string {
        if (str_starts_with($download->url, '/')) {
            return self::BASE_URL . $download->url;
        }
        return $download->url;
    }

    public function getFilename(
        DownloadDescription|GameExtra $download,
        int $httpTimeout = 3
    ): ?string {
        $url = $this->getRealDownloadUrl($download, $httpTimeout);
        if (!$url) {
            return null;
        }

        return urldecode(pathinfo($url, PATHINFO_BASENAME));
    }

    public function download(
        DownloadDescription|GameExtra $download,
        callable $callback,
        ?int $startAt = null,
        int $httpTimeout = 3,
        array $curlOptions = [],
    ): ResponseStreamInterface {
        $url = $this->getRealDownloadUrl($download, $httpTimeout);

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

    private function getRealDownloadUrl(
        DownloadDescription|GameExtra $download,
        int $httpTimeout = 3
    ): ?string {
        if (str_starts_with($download->url, '/')) {
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

        $response = $this->httpClient->request(
            Request::METHOD_GET,
            $this->getDownloadUrl($download),
            [
                'auth_bearer' => (string) $this->authentication->getAuthorization(),
                'max_redirects' => 0,
                'timeout' => $httpTimeout,
            ]
        );
        try {
            $content = json_decode($response->getContent(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== Response::HTTP_NOT_FOUND) {
                throw $e;
            }

            return null;
        }

        return $content['downlink'] ?? null;
    }
}
