<?php

namespace App\DTO;

use App\Attribute\ArrayType;
use JetBrains\PhpStorm\Deprecated;

final readonly class GameDetail
{
    /**
     * @param array<GameInstaller> $downloads
     * @param array<GameExtra> $extras
     */
    public function __construct(
        public int    $id,
        public string $title,
        #[Deprecated]
        public string $cdKey,
        #[ArrayType(type: GameInstaller::class)]
        public array  $downloads,
        public string $slug,
        public array $extras,
    ) {
    }

    public function __unserialize(array $data): void
    {
        $data['extras'] ??= [];
        $data['slug'] ??= '';

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
