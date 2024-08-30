<?php

namespace App\Service\Persistence;

use App\DTO\Authorization;
use App\DTO\GameDetail;
use App\Enum\Setting;
use App\Service\MigrationManager;
use DateTimeImmutable;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;

final class PersistenceManagerSqlite extends AbstractPersistenceManager
{
    private const DATABASE = 'gog-downloader.db';

    public function __construct(
        private readonly MigrationManager $migrationManager,
    ) {
    }

    public function getAuthorization(): ?Authorization
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $result = $pdo->query('select * from auth');
        if ($result === false) {
            return null;
        }
        $result = $result->fetch(PDO::FETCH_ASSOC);
        if ($result === false) {
            return null;
        }

        return new Authorization(
            $result['token'],
            $result['refreshToken'],
            new DateTimeImmutable($result['validUntil']),
        );
    }

    public function saveAuthorization(?Authorization $authorization): self
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $pdo->exec('DELETE FROM auth');
        if ($authorization === null) {
            return $this;
        }

        $prepared = $pdo->prepare('INSERT INTO auth (token, refreshToken, validUntil) VALUES (?, ?, ?)');
        $prepared->execute([
            $authorization->token,
            $authorization->refreshToken,
            $authorization->validUntil->format('c'),
        ]);

        return $this;
    }

    /**
     * @param array<GameDetail> $details
     */
    public function storeLocalGameData(array $details): void
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $pdo->exec('DELETE FROM games');

        foreach ($details as $detail) {
            $this->storeSingleGameDetail($detail);
        }
    }

    /**
     * @return array<GameDetail>|null
     */
    public function getLocalGameData(): ?array
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $query = $pdo->query('select * from games');
        if ($query === false) {
            return null;
        }

        $result = [];
        while ($next = $query->fetch(PDO::FETCH_ASSOC)) {
            $result[] = unserialize($next['serializedData']);
        }

        return $result;
    }

    public function storeSingleGameDetail(GameDetail $detail): void
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $prepared = $pdo->prepare('insert into games (serializedData) VALUES (?)');
        $prepared->execute([
            serialize($detail),
        ]);
    }

    public function storeSetting(Setting $setting, float|bool|int|string|null $value): void
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $prepared = $pdo->prepare('insert into settings (setting, value) VALUES (?, ?)');
        $prepared->execute([
            $setting->value,
            json_encode($value),
        ]);
    }

    public function getSetting(Setting $setting): int|string|float|bool|null
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $settingName = $setting->value;
        $prepared = $pdo->prepare('select value from settings where setting = ?');
        $prepared->bindParam(1, $settingName);

        return $prepared->fetch()['value'] ?? null;
    }

    public function storeUncompressedHash(string $compressedHash, string $uncompressedHash): void
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $pdo->prepare('insert or ignore into compressed_file_hashes (compressed, uncompressed) values (?, ?)')->execute([
            $compressedHash,
            $uncompressedHash,
        ]);
    }

    public function getCompressedHash(string $uncompressedHash): ?string
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $query = $pdo->prepare("select compressed from compressed_file_hashes where uncompressed = ?");
        $query->bindParam(1, $uncompressedHash);
        $query->execute();

        $result = $query->fetch();

        return $result['compressed'] ?? null;
    }

    private function getPdo(
        #[ExpectedValues(valuesFromClass: self::class)]
        string $file
    ): PDO {
        return new PDO("sqlite:{$this->getFullPath($file)}");
    }
}
