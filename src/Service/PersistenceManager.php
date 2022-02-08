<?php

namespace App\Service;

use App\DTO\Authorization;
use App\DTO\GameDetail;
use JetBrains\PhpStorm\ExpectedValues;

final class PersistenceManager
{
    private const AUTHORIZATION_FILE = 'auth.txt';

    private const LOCAL_DATA_FILE = 'local.txt';

    public function getAuthorization(): ?Authorization
    {
        $path = $this->getFullPath(self::AUTHORIZATION_FILE);
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return unserialize($content);
    }

    public function saveAuthorization(?Authorization $authorization): self
    {
        $path = $this->getFullPath(self::AUTHORIZATION_FILE);
        file_put_contents($path, serialize($authorization));

        return $this;
    }

    /**
     * @param array<GameDetail> $details
     */
    public function storeLocalGameData(array $details): void
    {
        $path = $this->getFullPath(self::LOCAL_DATA_FILE);
        file_put_contents($path, serialize($details));
    }

    /**
     * @return array<GameDetail>|null
     */
    public function getLocalGameData(): ?array
    {
        $path = $this->getFullPath(self::LOCAL_DATA_FILE);
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return unserialize($content) ?: null;
    }

    private function getFullPath(
        #[ExpectedValues(valuesFromClass: self::class)]
        string $file
    ): string {
        return sprintf('%s/%s', $_ENV['CONFIG_DIRECTORY'] ?? getcwd(), $file);
    }
}
