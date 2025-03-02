<?php

namespace App\DTO;

final class GameExtra
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $size,
        public readonly string $url,
        public readonly int $gogGameId,
        public private(set) ?string $md5,
    ) {
    }
}
