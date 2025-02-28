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

        foreach ($this->getMigrationsToApply($pdo) as $migration) {
            $migration->migrate($pdo);
            $statement = $pdo->prepare('INSERT INTO migrations (version, created) VALUES (?, ?)');
            $statement->execute([
                $migration->getVersion(),
                (new DateTimeImmutable())->format('c'),
            ]);
        }

        $this->applied = true;
    }

    public function countUnappliedMigrations(PDO $pdo): int
    {
        return count($this->getMigrationsToApply($pdo));
    }

    public function countAllMigrations(): int
    {
        $migrations = is_countable($this->migrations) ? $this->migrations : [...$this->migrations];

        return count($migrations);
    }

    /**
     * @return array<Migration>
     */
    private function getMigrationsToApply(PDO $pdo): array
    {
        $migrations = [...$this->migrations];
        usort(
            $migrations,
            fn (Migration $migration1, Migration $migration2) => $migration1->getVersion() <=> $migration2->getVersion(),
        );

        return array_filter(
            $migrations,
            fn (Migration $migration) => !$this->isApplied($migration, $pdo),
        );
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
