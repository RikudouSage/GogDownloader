<?php

namespace App\Service;

use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NewVersionChecker
{
    private ?string $cached = null;

    public function __construct(
        #[Autowire('%app.source_repository%')]
        private readonly string $repository,
        private readonly Application $application,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function newVersionAvailable(): bool
    {
        $current = $this->getCurrentVersion();
        if ($current === 'dev-version') {
            return false;
        }

        $latest = $this->getLatestVersion();

        return version_compare($current, $latest, '<');
    }

    public function getLatestVersion(): string
    {
        if ($this->cached) {
            return $this->cached;
        }

        $url = "{$this->repository}/releases/latest";
        $response = $this->httpClient->request(
            Request::METHOD_HEAD,
            $url,
            [
                'max_redirects' => 0,
            ],
        );
        $location = $response->getHeaders(false)['location'][0] ?? null;
        $parts = explode('/', $location);
        $last = $parts[array_key_last($parts)];

        $this->cached = substr($last, 1);

        return $this->cached;
    }

    private function getCurrentVersion(): string
    {
        return $this->application->getVersion();
    }
}
