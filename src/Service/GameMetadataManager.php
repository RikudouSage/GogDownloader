<?php

namespace App\Service;

use App\DTO\Authorization;
use App\DTO\BuildInfoItem;
use App\DTO\GameDetail;
use App\DTO\GameInfo;
use App\DTO\OAuthCredentials;
use DateInterval;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GameMetadataManager
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private AuthenticationManager $authenticationManager,
        private Serializer $serializer,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function getGameCredentials(GameInfo|GameDetail $game): ?Authorization
    {
        $oauthData = $this->getGameOAuthCredentials($game);
        if ($oauthData === null) {
            return null;
        }

        return $this->authenticationManager->getGameAuthorization($oauthData->clientId, $oauthData->clientSecret);
    }

    public function getGameOAuthCredentials(GameInfo|GameDetail $game): ?OAuthCredentials
    {
        $cacheItem = $this->cache->getItem("game.oauth.credentials.{$game->id}");
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $builds = $this->getBuildsInfo($game);
        if (!count($builds)) {
            return null;
        }

        $result =  json_decode(zlib_decode($this->httpClient->request(
            Request::METHOD_GET,
            $builds[0]->link,
        )->getContent()), true);

        $clientId = $result['clientId'] ?? null;
        $clientSecret = $result['clientSecret'] ?? null;

        if ($clientId === null || $clientSecret === null) {
            return null;
        }

        $result = new OAuthCredentials($clientId, $clientSecret);
        $cacheItem->set($result);
        $cacheItem->expiresAfter(new DateInterval('PT1H'));
        $this->cache->save($cacheItem);

        return $result;
    }

    /**
     * @return array<BuildInfoItem>
     */
    private function getBuildsInfo(GameInfo|GameDetail $game): array
    {
        $json = json_decode($this->httpClient->request(
            Request::METHOD_GET,
            "https://content-system.gog.com/products/{$game->id}/os/windows/builds?generation=2"
        )->getContent(), true);

        return array_map(
            fn (array $build) => $this->serializer->deserialize($build, BuildInfoItem::class),
            $json['items'],
        );
    }
}
