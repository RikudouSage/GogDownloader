<?php

namespace App\Migration;

use App\Enum\NamingConvention;
use App\Enum\Setting;
use PDO;

final class Migration1 implements Migration
{
    public function migrate(PDO $pdo): void
    {
        $pdo->exec('create table settings (id integer primary key autoincrement, setting text, value text)');
        $pdo->prepare("insert into settings (setting, value) values (?, ?)")->execute([
            Setting::NamingConvention->value,
            json_encode(NamingConvention::GogSlug->value),
        ]);
    }

    public function getVersion(): int
    {
        return 1;
    }
}
