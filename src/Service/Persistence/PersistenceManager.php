<?php

namespace App\Service\Persistence;

use App\DTO\Authorization;
use App\DTO\GameDetail;
use App\Enum\Setting;

interface PersistenceManager
{
    public function getAuthorization(): ?Authorization;

    public function saveAuthorization(?Authorization $authorization): self;

    /**
     * @param array<GameDetail> $details
     */
    public function storeLocalGameData(array $details): void;

    /**
     * @return array<GameDetail>|null
     */
    public function getLocalGameData(): ?array;

    public function storeSingleGameDetail(GameDetail $detail): void;

    public function storeSetting(Setting $setting, int|string|float|bool|null $value): void;

    public function getSetting(Setting $setting): int|string|float|bool|null;

    public function storeUncompressedHash(string $compressedHash, string $uncompressedHash): void;

    public function getCompressedHash(string $uncompressedHash): ?string;
}
