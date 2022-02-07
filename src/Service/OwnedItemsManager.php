<?php

namespace App\Service;

use App\DTO\DownloadDescription;
use App\DTO\GameDetail;
use App\DTO\GameInfo;
use App\DTO\MovieInfo;
use App\DTO\OwnedItemInfo;
use App\DTO\Url;
use App\Enum\Language;
use App\Enum\MediaType;
use App\Enum\OperatingSystem;
use App\Exception\AuthorizationException;
use JsonException;
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
    ) {
    }

    /**
     * @param MediaType            $mediaType
     * @param Language|null        $language
     * @param OperatingSystem|null $operatingSystem
     * @param int|null             $productsCount
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
        ?Language $language = null,
        ?OperatingSystem $operatingSystem = null,
        int &$productsCount = null,
    ): iterable {
        $page = 1;
        $query = [
            'mediaType' => $mediaType->value,
            'page' => $page,
        ];
        if ($language !== null) {
            $query['language'] = $language->value;
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

    public function getItemDetail(OwnedItemInfo $item)
    {
        return match ($item->getType()) {
            MediaType::Game => $this->getGameDetail($item),
            MediaType::Movie => $this->getMovieDetail($item),
        };
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
        $data = $this->getLocalGameData();
        $data[] = $detail;
        $this->storeGamesData($data);
    }

    /**
     * @return array<GameDetail>
     */
    public function getLocalGameData(): array
    {
        return $this->persistence->getLocalGameData() ?? [];
    }

    public function getChecksum(DownloadDescription $download, GameDetail $game): ?string
    {
        $parts = explode('/', $download->url);
        $id = $parts[array_key_last($parts)];
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            new Url(
                host: self::GOG_API_URL,
                path: "products/{$game->id}",
                query: [
                    'expand' => 'downloads',
                ]
            ),
            [
                'auth_bearer' => (string) $this->authorization->getAuthorization(),
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
                ]
            );
            $response = json_decode($response->getContent(), true);

            $response = $this->httpClient->request(
                Request::METHOD_GET,
                $response['checksum'],
            );

            try {
                $response = new SimpleXMLElement($response->getContent());
            } catch (ClientExceptionInterface) {
                return null;
            }

            return (string) $response['md5'];
        }

        return null;
    }

    private function getGameDetail(OwnedItemInfo $item): GameDetail
    {
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            new Url(
                host: self::GOG_ACCOUNT_URL,
                path: "gameDetails/{$item->id}.json",
            ),
            [
                'auth_bearer' => (string) $this->authorization->getAuthorization(),
            ]
        );

        $detail = $this->serializer->deserialize($response->getContent(), GameDetail::class, [
            'id' => $item->getId(),
        ]);
        foreach ($detail->downloads as $download) {
            $this->setMd5($download, $detail);
        }
        assert($detail instanceof GameDetail);

        return $detail;
    }

    private function getMovieDetail(OwnedItemInfo $item)
    {
        $response = $this->httpClient->request(
            Request::METHOD_GET,
            new Url(
                host: self::GOG_ACCOUNT_URL,
                path: "movieDetails/{$item->id}.json",
            ),
            [
                'auth_bearer' => (string) $this->authorization->getAuthorization(),
            ]
        );

        $detail = $this->serializer->deserialize($response->getContent(), GameDetail::class, [
            'id' => $item->getId(),
        ]);
        assert($detail instanceof GameDetail);

        return $detail;
    }

    private function setMd5(DownloadDescription $download, GameDetail $game)
    {
        $md5 = $this->getChecksum($download, $game);
        if ($md5 === null) {
            $md5 = '';
        }
        $reflection = new ReflectionProperty($download, 'md5');
        $reflection->setValue($download, $md5);
    }
}
