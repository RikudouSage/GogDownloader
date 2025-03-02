<?php

namespace App\Migration;

use PDO;

final readonly class Migration6 implements Migration
{

    public function migrate(PDO $pdo): void
    {
        $pdo->exec('create table game_extras (
            id integer primary key autoincrement, 
            extra_id integer unique,
            name text,
            size integer,
            url text,
            gog_game_id integer,
            game_id integer,
            foreign key (game_id) references games(id) on delete cascade on update cascade
         )');
    }

    public function getVersion(): int
    {
        return 6;
    }
}
