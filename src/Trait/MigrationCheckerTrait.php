<?php

namespace App\Trait;

use App\Service\Persistence\PersistenceManager;
use Symfony\Component\Console\Output\OutputInterface;

trait MigrationCheckerTrait
{
    private function showInfoIfMigrationsAreNeeded(OutputInterface $output, PersistenceManager $persistence): void
    {
        if (!$persistence->needsMigrating(true)) {
            return;
        }

        $output->writeln("<warning>> The database needs migrating after an update, this command might take more time than usual, please don't cancel it in the middle of a migration.</warning>");
    }
}
