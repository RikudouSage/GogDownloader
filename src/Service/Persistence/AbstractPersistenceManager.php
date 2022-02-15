<?php

namespace App\Service\Persistence;

use JetBrains\PhpStorm\ExpectedValues;

abstract class AbstractPersistenceManager implements PersistenceManager
{
    protected function getFullPath(
        #[ExpectedValues(valuesFromClass: self::class)]
        string $file
    ): string {
        return sprintf('%s/%s', $_ENV['CONFIG_DIRECTORY'] ?? getcwd(), $file);
    }
}