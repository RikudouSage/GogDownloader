<?php

namespace App\Migration;

use App\Enum\NamingConvention;
use App\Enum\Setting;
use PDO;

final readonly class Migration4 implements Migration
{

    public function migrate(PDO $pdo): void
    {
        $pdo->exec('alter table games add column slug text default null');
        // delete duplicate settings
        $pdo->exec('delete from settings where id in (select max(id) from settings group by setting having count(setting) > 1)');
        $pdo->exec("alter table settings rename to old_settings");
        $pdo->exec('create table settings (id integer primary key autoincrement, setting text, value text, constraint setting_name unique (setting))');
        $pdo->exec('insert into settings (setting, value) select setting, value from old_settings');
        $pdo->exec('drop table old_settings');

        // A new default value has been retroactively added to Migration1.
        // The original naming convention is being set here if there isn't one.
        // This effectively means that all new installations have the new value while everyone who used the app before
        // has the original one.
        $pdo->prepare('insert into settings (setting, value) values (?, ?) on conflict(setting) do nothing')->execute([
            Setting::NamingConvention->value,
            json_encode(NamingConvention::Custom->value),
        ]);
    }

    public function getVersion(): int
    {
        return 4;
    }
}
