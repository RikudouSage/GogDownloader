<?php

namespace App\DTO;

use App\Attribute\ArrayType;

final readonly class GameDetail
{
    /**
     * @param  array<DownloadDescription> $downloads
     */
    public function __construct(
        public int    $id,
        public string $title,
        public string $cdKey,
        #[ArrayType(type: DownloadDescription::class)]
        public array  $downloads,
    ) {
    }
}
