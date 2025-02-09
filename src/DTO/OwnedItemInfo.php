<?php

namespace App\DTO;

use App\Enum\MediaType;

interface OwnedItemInfo
{
    public function getId(): int;

    public function getTitle(): string;

    public function getType(): MediaType;

    public function hasUpdates(): bool;

    public function getSlug(): string;
}
