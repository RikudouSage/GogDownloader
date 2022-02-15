<?php

namespace App\Service\Persistence;

use App\DTO\Authorization;
use App\DTO\GameDetail;

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
}
