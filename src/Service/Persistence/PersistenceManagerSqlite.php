<?php

namespace App\Service\Persistence;

use App\DTO\Authorization;
use App\DTO\GameDetail;
use App\Enum\Setting;
use App\Service\MigrationManager;
use App\Service\Serializer;
use DateTimeImmutable;
use JetBrains\PhpStorm\ExpectedValues;
use PDO;

final class PersistenceManagerSqlite extends AbstractPersistenceManager
{
    private const DATABASE = 'gog-downloader.db';

    public function __construct(
        private readonly MigrationManager $migrationManager,
        private readonly Serializer $serializer,
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
            $downloadsQuery = $pdo->prepare('select * from downloads where game_id = ?');
            $downloadsQuery->execute([$next['id']]);
            $downloads = $downloadsQuery->fetchAll(PDO::FETCH_ASSOC);

            $extrasQuery = $pdo->prepare('select * from game_extras where game_id = ?');
            $extrasQuery->execute([$next['id']]);
            $extras = $extrasQuery->fetchAll(PDO::FETCH_ASSOC);

            $result[] = $this->serializer->deserialize([
                'id' => $next['game_id'],
                'title' => $next['title'],
                'cdKey' => $next['cd_key'] ?? '',
                'downloads' => $downloads,
                'slug' => $next['slug'] ?? '',
                'extras' => $extras,
            ], GameDetail::class);
        }

        return $result;
    }

    public function storeSingleGameDetail(GameDetail $detail): void
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $pdo->prepare(
            'insert into games (title, cd_key, game_id, slug)
                   VALUES (?, ?, ?, ?)
                   ON CONFLICT DO UPDATE SET title   = excluded.title,
                                             cd_key  = excluded.cd_key,
                                             game_id = excluded.game_id,
                                             slug    = excluded.slug
                   '
        )->execute([
            $detail->title,
            $detail->cdKey ?: null,
            $detail->id,
            $detail->slug,
        ]);

        $query = $pdo->prepare('select id from games where game_id = ?');
        $query->execute([$detail->id]);
        $id = $query->fetch(PDO::FETCH_ASSOC)['id'];

        $pdo->prepare('delete from downloads where game_id = ?')->execute([$id]);
        foreach ($detail->downloads as $download) {
            $pdo->prepare('insert into downloads (language, platform, name, size, url, md5, game_id, gog_game_id, is_patch) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $download->language,
                $download->platform,
                $download->name,
                $download->size,
                $download->url,
                $download->md5,
                $id,
                $download->gogGameId,
                (int) $download->isPatch,
            ]);
        }
        foreach ($detail->extras as $extra) {
            $pdo->prepare('insert into game_extras (extra_id, name, size, url, gog_game_id, game_id, md5)
                                 values (?, ?, ?, ?, ?, ?, ?)
                                 on conflict (extra_id) do update set name        = excluded.name,
                                                                      size        = excluded.size,
                                                                      url         = excluded.url,
                                                                      gog_game_id = excluded.gog_game_id,
                                                                      game_id     = excluded.game_id,
                                                                      md5         = excluded.md5
            ')->execute([
                $extra->id,
                $extra->name,
                $extra->size,
                $extra->url,
                $extra->gogGameId,
                $id,
                $extra->md5,
            ]);
        }
    }

    public function storeSetting(Setting $setting, float|bool|int|string|null $value): void
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $settingName = $setting->value;
        $preparedSelect = $pdo->prepare('select count(id) from settings where setting = ?');
        $preparedSelect->bindParam(1, $settingName);
        $preparedSelect->execute();

        $result = $preparedSelect->fetch(PDO::FETCH_NUM);

        if (!$result[0]) {
            $prepared = $pdo->prepare('insert into settings (setting, value) VALUES (?, ?)');
            $prepared->execute([
                $setting->value,
                json_encode($value),
            ]);
        } else {
            $prepared = $pdo->prepare('update settings set value = ? where setting = ?');
            $prepared->execute([
                json_encode($value),
                $setting->value,
            ]);
        }
    }

    public function getSetting(Setting $setting): int|string|float|bool|null
    {
        $pdo = $this->getPdo(self::DATABASE);
        $this->migrationManager->apply($pdo);

        $settingName = $setting->value;
        $prepared = $pdo->prepare('select value from settings where setting = ?');
        $prepared->bindParam(1, $settingName);
        $prepared->execute();

        $result = $prepared->fetch(PDO::FETCH_ASSOC);

        return isset($result['value']) ? json_decode($result['value']) : null;
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

        $query = $pdo->prepare('select compressed from compressed_file_hashes where uncompressed = ?');
        $query->bindParam(1, $uncompressedHash);
        $query->execute();

        $result = $query->fetch();

        return $result['compressed'] ?? null;
    }

    public function needsMigrating(bool $excludeEmpty = false): bool
    {
        $pdo = $this->getPdo(self::DATABASE);
        $count = $this->migrationManager->countUnappliedMigrations($pdo);
        if ($excludeEmpty && $count === $this->migrationManager->countAllMigrations()) {
            return false;
        }

        return $count > 0;
    }

    private function getPdo(
        #[ExpectedValues(valuesFromClass: self::class)]
        string $file
    ): PDO {
        $pdo = new PDO("sqlite:{$this->getFullPath($file)}");
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
