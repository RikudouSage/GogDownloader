<?php

namespace App\Service\Persistence;

use App\DTO\Authorization;
use App\DTO\GameDetail;
use App\Enum\Setting;
use Error;
use ReflectionObject;
use Stringable;

final class PersistenceManagerFiles extends AbstractPersistenceManager
{
    private const AUTH_FILE = 'auth.db';

    private const GAME_FILE = 'games.db';

    private const SETTINGS_FILE = 'settings.db';

    private const HASHES_FILE = 'hashes.db';

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
            $this->serialize($authorization),
        );

        return $this;
    }

    public function storeLocalGameData(array $details): void
    {
        file_put_contents(
            $this->getFullPath(self::GAME_FILE),
            $this->serialize($details),
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

    public function storeSetting(Setting $setting, float|bool|int|string|null $value): void
    {
        $file = $this->getFullPath(self::SETTINGS_FILE);
        $content = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        $content[$setting->value] = $value;

        file_put_contents($file, json_encode($content));
    }

    public function getSetting(Setting $setting): int|string|float|bool|null
    {
        $file = $this->getFullPath(self::SETTINGS_FILE);
        if (!file_exists($file)) {
            return null;
        }
        $content = json_decode(file_get_contents($file), true);

        return $content[$setting->value] ?? null;
    }

    public function getCompressedHash(string $uncompressedHash): ?string
    {
        $file = $this->getFullPath(self::HASHES_FILE);
        if (!file_exists($file)) {
            return null;
        }
        $content = json_decode(file_get_contents($file), true);

        return $content[$uncompressedHash] ?? null;
    }

    public function storeUncompressedHash(string $compressedHash, string $uncompressedHash): void
    {
        $file = $this->getFullPath(self::HASHES_FILE);
        $content = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        $content[$uncompressedHash] = $compressedHash;

        file_put_contents($file, json_encode($content));
    }

    public function needsMigrating(bool $excludeEmpty = false): bool
    {
        return false;
    }

    private function serialize(mixed $itemToSerialize): string
    {
        return serialize($this->normalize($itemToSerialize));
    }

    /**
     * @template T of mixed
     *
     * @param T $itemToNormalize
     * @return T
     */
    private function normalize(mixed $itemToNormalize): mixed
    {
        if (is_array($itemToNormalize)) {
            foreach ($itemToNormalize as $key => $item) {
                $itemToNormalize[$key] = $this->normalize($item);
            }

            return $itemToNormalize;
        } else if (is_object($itemToNormalize)) {
            $reflection = new ReflectionObject($itemToNormalize);
            $copy = $reflection->newInstanceWithoutConstructor();
            foreach ($reflection->getProperties() as $property) {
                try {
                    $currentValue = $property->getValue($itemToNormalize);
                    if ($currentValue instanceof Stringable) {
                        $currentValue = (string) $currentValue;
                    } else if (is_array($currentValue)) {
                        $currentValue = $this->normalize($currentValue);
                    }
                    $property->setValue($copy, $currentValue);
                } catch (Error) {
                    continue;
                }
            }

            return $copy;
        }

        return $itemToNormalize;
    }
}
