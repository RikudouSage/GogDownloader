<?php

namespace App\Service;

use App\DTO\DownloadableItem;
use App\DTO\GameDetail;
use App\DTO\GameInfo;
use App\DTO\MovieInfo;
use App\DTO\OwnedItemInfo;
use App\DTO\SearchFilter;
use App\DTO\Url;
use App\Enum\Language;
use App\Enum\MediaType;
use App\Enum\OperatingSystem;
use App\Exception\AuthorizationException;
use App\Service\Persistence\PersistenceManager;
use DateInterval;
use Exception;
use JsonException;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionException;
use ReflectionProperty;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OwnedItemsManager
{
    private const GOG_ACCOUNT_URL = 'https://embed.gog.com/account';

    private const GOG_API_URL = 'https://api.gog.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AuthenticationManager $authorization,
        private readonly Serializer $serializer,
        private readonly PersistenceManager $persistence,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param MediaType    $mediaType
     * @param SearchFilter $filter
     * @param int|null     $productsCount
     *
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws RedirectionExceptionInterface
     * @throws ReflectionException
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     *
     * @return iterable<OwnedItemInfo>
     */
    public function getOwnedItems(
        MediaType $mediaType,
        SearchFilter $filter = new SearchFilter(),
        ?int &$productsCount = null,
        int $httpTimeout = 3,
    ): iterable {
        $page = 1;
        $query = [
            'mediaType' => $mediaType->value,
            'page' => $page,
            'sortBy' => 'title',
            'hiddenFlag' => 0,
        ];
        if ($filter->languages !== null) {
            $query['language'] = implode(',', array_map(
                fn (Language $language): string => $language->value,
                $filter->languages,
            ));
        }
        if ($filter->operatingSystems !== null) {
            $query['system'] = implode(',', array_map(
                fn (OperatingSystem $operatingSystem): string => $operatingSystem->getAsNumbers(),
                $filter->operatingSystems,
            ));
        }
        if ($filter->search !== null) {
            $query['search'] = $filter->search;
        }
        if ($filter->includeHidden) {
            unset($query['hiddenFlag']);
        }

        do {
            $response = $this->httpClient->request(
                Request::METHOD_GET,
                new Url(
                    host: self::GOG_ACCOUNT_URL,
                    path: 'getFilteredProducts',
                    query: $query,
                ),
                [
                    'auth_bearer' => (string) $this->authorization->getAuthorization(),
                    'timeout' => $httpTimeout,
                ]
            );

            try {
                $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
                ++$query['page'];
                $productsCount = $data['totalProducts'];
            } catch (JsonException) {
                throw new AuthorizationException('Failed to get list of games, you were probably logged out.');
            }

            foreach ($data['products'] as $product) {
                if ($product['isGame']) {
                    yield $this->serializer->deserialize($product, GameInfo::class);
                }
                if ($product['isMovie']) {
                    yield $this->serializer->deserialize($product, MovieInfo::class);
                }
            }
        } while ($query['page'] <= $data['totalPages']);
    }

    public function getGameDetailByTitle(string $title): ?GameDetail
    {
        return array_find(
            $this->getLocalGameData(),
            fn ($gameDetail) => $gameDetail->title === $title,
        );
    }

    public function getItemDetail(OwnedItemInfo $item, int $httpTimeout = 3, bool $cached = true): ?GameDetail
    {
        $cacheItem = $cached ? $this->cache->getItem("game_detail.{$item->getType()->value}.{$item->getId()}") : null;
        if ($cacheItem?->isHit()) {
            return $cacheItem->get();
        }

        $detail = match ($item->getType()) {
            MediaType::Game => $this->getGameDetail($item, $httpTimeout),
            MediaType::Movie => $this->getMovieDetail($item, $httpTimeout),
        };

        $cacheItem?->set($detail);
        $cacheItem?->expiresAfter(new DateInterval('PT20M'));
        if ($cacheItem) {
            $this->cache->save($cacheItem);
        }

        return $detail;
    }

    /**
     * @param array<GameDetail> $details
     */
    public function storeGamesData(array $details): void
    {
        $this->persistence->storeLocalGameData($details);
    }

    public function storeSingleGameData(GameDetail $detail): void
    {
        $this->persistence->storeSingleGameDetail($detail);
    }

    /**
     * @return array<GameDetail>
     */
    public function getLocalGameData(): array
    {
        return $this->persistence->getLocalGameData() ?? [];
    }

    /**
     * @todo move this to deserialization (duplicate requests)
     */
    public function getChecksum(DownloadableItem $download, ?GameDetail $game, int $httpTimeout = 3): ?string
    {
        $parts = explode('/', $download->url);
        $id = $parts[array_key_last($parts)];
        $gameId = $download->gogGameId ?? $game?->id;

        $response = $this->httpClient->request(
            Request::METHOD_GET,
            new Url(
                host: self::GOG_API_URL,
                path: "products/{$gameId}",
                query: [
                    'expand' => 'downloads',
                ]
            ),
            [
                'auth_bearer' => (string) $this->authorization->getAuthorization(),
                'timeout' => $httpTimeout,
            ]
        );

        $data = json_decode($response->getContent(), true)['downloads'];
        foreach ($data as $items) {
            $targetUrl = array_map(
                fn (array $item) => $item['files'],
                $items,
            );
            $targetUrl = array_merge(...$targetUrl);
            $targetUrl = array_filter($targetUrl, fn (array $item) => str_ends_with($item['downlink'], $id));
            $targetUrl = array_values($targetUrl)[0]['downlink'] ?? null;
            if ($targetUrl === null) {
                continue;
            }

            $response = $this->httpClient->request(
                Request::METHOD_GET,
                $targetUrl,
                [
                    'auth_bearer' => (string) $this->authorization->getAuthorization(),
                    'timeout' => $httpTimeout,
                ]
            );
            $response = json_decode($response->getContent(), true);

            $response = $this->httpClient->request(
                Request::METHOD_GET,
                $response['checksum'],
                [
                    'timeout' => $httpTimeout,
                ]
            );

            try {
                $response = new SimpleXMLElement($response->getContent());
            } catch (ClientExceptionInterface | TransportExceptionInterface | ServerExceptionInterface) {
                return null;
            } catch (Exception $e) {
                if (!str_contains($e->getMessage(), 'String could not be parsed as XML')) {
                    throw $e;
                }

                return null;
            }

            return (string) $response['md5'];
        }

        return null;
    }

    private function getGameDetail(OwnedItemInfo $item, int $httpTimeout): ?GameDetail
    {
        $ownedResponse = $this->httpClient->request(
            Request::METHOD_GET,
            new Url(
                host: self::GOG_ACCOUNT_URL,
                path: "gameDetails/{$item->id}.json",
            ),
            [
                'auth_bearer' => (string) $this->authorization->getAuthorization(),
                'timeout' => $httpTimeout,
            ]
        );
        $ownedContent = json_decode($ownedResponse->getContent(), true);
        if (isset($ownedContent['isBaseProductMissing']) && $ownedContent['isBaseProductMissing']) {
            return null;
        }
        $ownedDlcTitles = array_map(
            fn (array $item) => $item['title'],
            $ownedContent['dlcs'],
        );
        $ownedExtrasTitles = array_map(
            fn (array $item) => $item['name'],
            $ownedContent['extras'],
        );

        $generalResponse = $this->httpClient->request(
            Request::METHOD_GET,
            new Url(
                host: self::GOG_API_URL,
                path: "products/{$item->id}",
                query: [
                    'expand' => implode(',', [
                        'downloads',
                        'expanded_dlcs',
                    ])
                ]
            ),
            [
                'auth_bearer' => (string) $this->authorization->getAuthorization(),
                'timeout' => $httpTimeout,
            ]
        );

        $generalContent = json_decode($generalResponse->getContent(), true);
        $generalContent['expanded_dlcs'] = array_filter(
            $generalContent['expanded_dlcs'] ?? [],
            fn (array $item) => in_array($item['title'], $ownedDlcTitles, true),
        );
        $generalContent['downloads']['bonus_content'] = array_filter(
            $generalContent['downloads']['bonus_content'] ?? [],
            fn (array $item) => in_array($item['name'], $ownedExtrasTitles, true),
        );

        $detail = $this->serializer->deserialize($generalContent, GameDetail::class, [
            'id' => $item->getId(),
            'slug' => $item->getSlug(),
        ]);
        foreach ($detail->downloads as $download) {
            $this->setMd5($download, $detail, $httpTimeout);
        }
        foreach ($detail->extras as $extra) {
            $this->setMd5($extra, $detail, $httpTimeout);
        }
        assert($detail instanceof GameDetail);

        return $detail;
    }

    private function getMovieDetail(OwnedItemInfo $item, int $httpTimeout): GameDetail
    {
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            new Url(
                host: self::GOG_ACCOUNT_URL,
                path: "movieDetails/{$item->id}.json",
            ),
            [
                'auth_bearer' => (string) $this->authorization->getAuthorization(),
                'timeout' => $httpTimeout,
            ]
        );

        $detail = $this->serializer->deserialize($response->getContent(), GameDetail::class, [
            'id' => $item->getId(),
        ]);
        assert($detail instanceof GameDetail);

        return $detail;
    }

    private function setMd5(DownloadableItem $download, GameDetail $game, int $httpTimeout): void
    {
        if ($download->md5 !== null) {
            return;
        }
        $md5 = $this->getChecksum($download, $game, $httpTimeout);
        if ($md5 === null) {
            $md5 = '';
        }
        $reflection = new ReflectionProperty($download, 'md5');
        $reflection->setValue($download, $md5);
    }
}
