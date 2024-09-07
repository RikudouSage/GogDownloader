<?php

namespace App\Service;

use App\DTO\GameDetail;
use App\DTO\GameInfo;
use App\DTO\SaveFileContent;
use App\DTO\SaveGameFile;
use DateInterval;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CloudSavesManager
{
    private const BASE_URL = 'https://cloudstorage.gog.com/v1';

    public function __construct(
        private AuthenticationManager  $authenticationManager,
        private HttpClientInterface    $httpClient,
        private GameMetadataManager    $gameMetadataManager,
        private string                 $userAgent,
        private Serializer             $serializer,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function supports(GameInfo|GameDetail $game): bool
    {
        $cacheItem = $this->cache->getItem("game.saves.supported.{$game->id}");
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $oauth = $this->gameMetadataManager->getGameOAuthCredentials($game);
        if ($oauth === null) {
            return false;
        }

        $url = "https://remote-config.gog.com/components/galaxy_client/clients/{$oauth->clientId}?component_version=2.0.43";
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            $url,
        );
        $json = json_decode($response->getContent(), true);

        $supported = ($json['content']['cloudStorage']['quota'] ?? 0) > 0;
        $cacheItem->set($supported);
        $cacheItem->expiresAfter(new DateInterval('P1D'));
        $this->cache->save($cacheItem);

        return $supported;
    }

    /**
     * @return array<SaveGameFile>
     */
    public function getGameSaves(GameInfo|GameDetail $game, bool $includeDeleted = false): array
    {
        $oauth = $this->gameMetadataManager->getGameOAuthCredentials($game);
        $credentials = $this->gameMetadataManager->getGameAccessKey($game);
        $url = sprintf(
            "%s/%s/%s",
            self::BASE_URL,
            $this->authenticationManager->getUserInfo()->galaxyUserId,
            $oauth->clientId,
        );
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            $url,
            [
                'auth_bearer' => (string) $credentials,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => "{$this->userAgent} dont_sync_marker/true",
                ],
            ],
        );
        $json = json_decode($response->getContent(), true);

        return array_filter(
            array_map(
                fn (array $saveFile) => $this->serializer->deserialize($saveFile, SaveGameFile::class),
                $json,
            ),
            fn (SaveGameFile $saveFile) => $includeDeleted || !$saveFile->isDeleted(),
        );
    }

    public function downloadSave(SaveGameFile $file, GameDetail|GameInfo $game): SaveFileContent
    {
        $oauth = $this->gameMetadataManager->getGameOAuthCredentials($game);
        $credentials = $this->gameMetadataManager->getGameAccessKey($game);
        $url = sprintf(
            "%s/%s/%s/%s",
            self::BASE_URL,
            $this->authenticationManager->getUserInfo()->galaxyUserId,
            $oauth->clientId,
            str_replace('#', '%23', $file->name),
        );

        $response = $this->httpClient->request(
            Request::METHOD_GET,
            $url,
            [
                'auth_bearer' => (string) $credentials,
                'headers' => [
                    'User-Agent' => "{$this->userAgent} dont_sync_marker/true",
                    'Accept-Encoding' => 'deflate, gzip',
                ],
            ],
        );

        $raw = $response->getContent();
        $hash = md5($raw);

        return new SaveFileContent(
            hash: $hash,
            content: gzdecode($raw),
        );
    }
}
