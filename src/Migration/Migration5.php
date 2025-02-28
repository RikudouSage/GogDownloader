<?php

namespace App\Migration;

use PDO;

final readonly class Migration5 implements Migration
{

    public function migrate(PDO $pdo): void
    {
        $pdo->exec('alter table downloads add column gog_game_id integer default null');
    }

    public function getVersion(): int
    {
        return 5;
    }
}
