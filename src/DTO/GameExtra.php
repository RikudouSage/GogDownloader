<?php

namespace App\DTO;

final readonly class GameExtra
{
    public function __construct(
        public int $id,
        public string $name,
        public int $size,
        public string $url,
        public int $gogGameId,
    ) {
    }
}
