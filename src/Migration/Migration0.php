<?php

namespace App\Migration;

use PDO;

final class Migration0 implements Migration
{
    public function migrate(PDO $pdo): void
    {
        $pdo->exec('create table migrations (id integer primary key autoincrement, version int, created text)');
        $pdo->exec('create table auth (id integer primary key autoincrement, token text, refreshToken text, validUntil text)');
        $pdo->exec('create table games (id integer primary key autoincrement, serializedData text)');
    }

    public function getVersion(): int
    {
        return 0;
    }
}
