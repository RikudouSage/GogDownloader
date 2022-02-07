<?php

namespace App\DTO;

use App\Attribute\ArrayType;

final class GameDetail
{
    public readonly int $id;

    public readonly string $title;

    public readonly string $cdKey;

    /**
     * @var array<DownloadDescription>
     */
    #[ArrayType(type: DownloadDescription::class)]
    public readonly array $downloads;
}
