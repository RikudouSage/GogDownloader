<?php

namespace App\Service;

use App\DTO\Authorization;
use App\DTO\GameDetail;
use DateTimeImmutable;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;

final class PersistenceManager
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

    public function storeSingleGameDetail(GameDetail $detail)
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $prepared = $pdo->prepare('insert into games (serializedData) VALUES (?)');
        $prepared->execute([
            serialize($detail),
        ]);
    }

    private function getFullPath(
        #[ExpectedValues(valuesFromClass: self::class)]
        string $file
    ): string {
        return sprintf('%s/%s', $_ENV['CONFIG_DIRECTORY'] ?? getcwd(), $file);
    }

    private function getPdo(
        #[ExpectedValues(valuesFromClass: self::class)]
        string $file
    ): PDO {
        return new PDO("sqlite:{$this->getFullPath($file)}");
    }
}
