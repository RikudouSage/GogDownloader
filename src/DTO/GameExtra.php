<?php

namespace App\DTO;

final class GameExtra implements DownloadableItem
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
