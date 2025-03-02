<?php

namespace App\DTO;

use App\Attribute\ArrayType;
use JetBrains\PhpStorm\Deprecated;

final readonly class GameDetail
{
    /**
     * @param  array<DownloadDescription> $downloads
     */
    public function __construct(
        public int    $id,
        public string $title,
        #[Deprecated]
        public string $cdKey,
        #[ArrayType(type: DownloadDescription::class)]
        public array  $downloads,
        public string $slug,
    ) {
    }
}
