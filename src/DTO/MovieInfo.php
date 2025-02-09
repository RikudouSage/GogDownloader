<?php

namespace App\DTO;

use App\Enum\MediaType;

final class MovieInfo implements OwnedItemInfo
{
    public readonly int $id;

    public readonly string $title;

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): MediaType
    {
        return MediaType::Movie;
    }

    public function hasUpdates(): bool
    {
        return false;
    }

    public function getSlug(): string
    {
        return '';
    }
}
