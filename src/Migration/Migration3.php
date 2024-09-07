<?php

namespace App\Migration;

use App\DTO\GameDetail;
use PDO;

final readonly class Migration3 implements Migration
{
    public function migrate(PDO $pdo): void
    {
        $originalRows = $pdo->query('select * from games')->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<GameDetail> $games */
        $games = array_map(
            fn (array $row) => unserialize($row['serializedData']),
            $originalRows,
        );

        $pdo->exec('drop table games');
        $pdo->exec('create table games (id integer primary key autoincrement, title string, cd_key string, game_id integer, unique (game_id))');
        $pdo->exec('create table downloads (
            id integer primary key autoincrement,
            language string,
            platform string,
            name string,
            size float,
            url string,
            md5 string,
            game_id integer
        )');

        foreach ($games as $game) {
            $pdo->prepare('insert into games (game_id, title, cd_key) values (?, ?, ?)')->execute([
                $game->id,
                $game->title,
                $game->cdKey ?: null,
            ]);
            $rowId = $pdo->lastInsertId();

            foreach ($game->downloads as $download) {
                $pdo->prepare('insert into downloads (language, platform, name, size, url, md5, game_id) values (?, ?, ?, ?, ?, ?, ?)')->execute([
                    $download->language,
                    $download->platform,
                    $download->name,
                    $download->size,
                    $download->url,
                    $download->md5,
                    $rowId,
                ]);
            }
        }
    }

    public function getVersion(): int
    {
        return 3;
    }
}
