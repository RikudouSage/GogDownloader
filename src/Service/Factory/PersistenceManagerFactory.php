<?php

namespace App\Service\Factory;

use App\Service\MigrationManager;
use App\Service\Persistence\PersistenceManager;
use App\Service\Persistence\PersistenceManagerFiles;
use App\Service\Persistence\PersistenceManagerSqlite;
use App\Service\Serializer;
use PDO;
use SQLite3;

final readonly class PersistenceManagerFactory
{
    public function __construct(
        private MigrationManager $migrationManager,
        private Serializer $serializer,
    ) {
    }

    public function getPersistenceManager(): PersistenceManager
    {
//        if (class_exists(PDO::class, false) && class_exists(SQLite3::class, false)) {
//            return new PersistenceManagerSqlite($this->migrationManager, $this->serializer);
//        }

        return new PersistenceManagerFiles();
    }
}
