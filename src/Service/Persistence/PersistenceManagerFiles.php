<?php

namespace App\Service\Persistence;

use App\DTO\Authorization;
use App\DTO\GameDetail;

final class PersistenceManagerFiles extends AbstractPersistenceManager
{
    private const AUTH_FILE = 'auth.db';

    private const GAME_FILE = 'games.db';

    public function getAuthorization(): ?Authorization
    {
        $file = $this->getFullPath(self::AUTH_FILE);
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        return @unserialize($content) ?: null;
    }

    public function saveAuthorization(?Authorization $authorization): self
    {
        file_put_contents(
            $this->getFullPath(self::AUTH_FILE),
            serialize($authorization),
        );

        return $this;
    }

    public function storeLocalGameData(array $details): void
    {
        file_put_contents(
            $this->getFullPath(self::GAME_FILE),
            serialize($details),
        );
    }

    public function getLocalGameData(): ?array
    {
        $file = $this->getFullPath(self::GAME_FILE);
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        return @unserialize($content) ?: null;
    }

    public function storeSingleGameDetail(GameDetail $detail): void
    {
        $details = $this->getLocalGameData();
        $details[] = $detail;
        $this->storeLocalGameData($details);
    }
}
