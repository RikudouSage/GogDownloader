<?php

namespace App\DTO;

use App\Attribute\SerializedName;
use App\Enum\MediaType;

final class GameInfo implements OwnedItemInfo
{
    public readonly int $id;

    public readonly string $title;

    #[SerializedName('updates')]
    public readonly ?bool $hasUpdates;

    public readonly bool $isNew;

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
        return MediaType::Game;
    }

    public function hasUpdates(): bool
    {
        return $this->hasUpdates || $this->isNew;
    }
}
