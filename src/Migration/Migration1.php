<?php

namespace App\Migration;

use PDO;

final class Migration1 implements Migration
{
    public function migrate(PDO $pdo): void
    {
        $pdo->exec('create table settings (id integer primary key autoincrement, setting text, value text)');
    }

    public function getVersion(): int
    {
        return 1;
    }
}
