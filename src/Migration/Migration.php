<?php

namespace App\Migration;

use PDO;

interface Migration
{
    public function migrate(PDO $pdo): void;

    public function getVersion(): int;
}
