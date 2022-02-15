<?php

namespace App\Service\Factory;

use App\Service\MigrationManager;
use App\Service\Persistence\PersistenceManager;
use App\Service\Persistence\PersistenceManagerFiles;
use App\Service\Persistence\PersistenceManagerSqlite;

final class PersistenceManagerFactory
{
    public function __construct(
        private readonly MigrationManager $migrationManager,
    ) {
    }

    public function getPersistenceManager(): PersistenceManager
    {
        if (class_exists(\PDO::class, false) && class_exists(\SQLite3::class, false)) {
            return new PersistenceManagerSqlite($this->migrationManager);
        }

        return new PersistenceManagerFiles();
    }
}
