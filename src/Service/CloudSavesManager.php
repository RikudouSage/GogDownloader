<?php

namespace App\Service;

use App\DTO\GameDetail;
use App\DTO\GameInfo;
use App\DTO\SaveGameFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CloudSavesManager
{
    private const BASE_URL = 'https://cloudstorage.gog.com/v1';

    public function __construct(
        private AuthenticationManager $authenticationManager,
        private HttpClientInterface   $httpClient,
        private OwnedItemsManager     $ownedItemsManager,
        private GameMetadataManager   $gameMetadataManager,
        private string                $userAgent,
        private Serializer $serializer,
    ) {
    }

    public function list()
    {
        foreach ($this->ownedItemsManager->getLocalGameData() as $game) {
            var_dump($this->getGameSaves($game));exit;
        }
    }

    /**
     * @return array<SaveGameFile>
     */
    public function getGameSaves(GameInfo|GameDetail $game, bool $includeDeleted = false): array
    {
        $oauth = $this->gameMetadataManager->getGameOAuthCredentials($game);
        $credentials = $this->gameMetadataManager->getGameCredentials($game);
        $url = sprintf(
            "%s/%s/%s",
            self::BASE_URL,
            $this->authenticationManager->getUserInfo()->galaxyUserId,
            $oauth->clientId,
        );
        $json = json_decode($this->httpClient->request(
            Request::METHOD_GET,
            $url,
            [
                'auth_bearer' => (string) $credentials,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => "{$this->userAgent} dont_sync_marker/true",
                ],
            ],
        )->getContent(), true);

        return array_filter(
            array_map(
                fn (array $saveFile) => $this->serializer->deserialize($saveFile, SaveGameFile::class),
                $json,
            ),
            fn (SaveGameFile $saveFile) => $includeDeleted || !$saveFile->isDeleted(),
        );
    }
}
