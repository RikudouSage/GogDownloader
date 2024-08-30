<?php

namespace App\Migration;

use PDO;

final readonly class Migration2 implements Migration
{
    public function migrate(PDO $pdo): void
    {
        $pdo->exec('create table compressed_file_hashes (compressed string, uncompressed string, UNIQUE(compressed, uncompressed))');
    }

    public function getVersion(): int
    {
        return 2;
    }
}
