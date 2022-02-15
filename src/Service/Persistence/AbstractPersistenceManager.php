<?php

namespace App\Service\Persistence;

abstract class AbstractPersistenceManager implements PersistenceManager
{
    protected function getFullPath(string $file): string
    {
        return sprintf('%s/%s', $_ENV['CONFIG_DIRECTORY'] ?? getcwd(), $file);
    }
}
