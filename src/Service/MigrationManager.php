<?php

namespace App\Service;

use App\Migration\Migration;
use DateTimeImmutable;
use PDO;
use PDOException;

final class MigrationManager
{
    private bool $applied = false;

    /**
     * @param iterable<Migration> $migrations
     */
    public function __construct(
        private readonly iterable $migrations,
    ) {
    }

    public function apply(PDO $pdo): void
    {
        if ($this->applied) {
            return;
        }
        $migrations = [...$this->migrations];
        usort(
            $migrations,
            fn (Migration $migration1, Migration $migration2) => $migration1->getVersion() <=> $migration2->getVersion(),
        );

        foreach ($migrations as $migration) {
            assert($migration instanceof Migration);
            if (!$this->isApplied($migration, $pdo)) {
                $migration->migrate($pdo);
                $statement = $pdo->prepare('INSERT INTO migrations (version, created) VALUES (?, ?)');
                $statement->execute([
                    $migration->getVersion(),
                    (new DateTimeImmutable())->format('c'),
                ]);
            }
        }

        $this->applied = true;
    }

    private function isApplied(Migration $migration, PDO $pdo): bool
    {
        $version = $migration->getVersion();

        try {
            $result = $pdo->query('select * from migrations order by version desc');
            $currentVersion = $result->fetch(PDO::FETCH_ASSOC)['version'];
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'no such table')) {
                throw $e;
            }
            $currentVersion = -1;
        }

        return $version <= $currentVersion;
    }
}
