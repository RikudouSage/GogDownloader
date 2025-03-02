<?php

namespace App\Migration;

use PDO;

final readonly class Migration7 implements Migration
{
    public function migrate(PDO $pdo): void
    {
        $pdo->exec("alter table game_extras add md5 text");
    }

    public function getVersion(): int
    {
        return 7;
    }
}
