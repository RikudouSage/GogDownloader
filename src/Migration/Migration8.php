<?php

namespace App\Migration;

use PDO;

final readonly class Migration8 implements Migration
{
    public function migrate(PDO $pdo): void
    {
        $pdo->exec("alter table downloads add is_patch boolean default false");
    }

    public function getVersion(): int
    {
        return 8;
    }
}
