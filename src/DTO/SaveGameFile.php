<?php

namespace App\DTO;

use App\Attribute\SerializedName;
use DateTimeImmutable;
use DateTimeInterface;

final readonly class SaveGameFile
{
    public int $bytes;
    #[SerializedName('last_modified')]
    public string $lastModified;
    public ?string $hash;
    public string $name;
    #[SerializedName('content_type')]
    public string $contentType;

    public function isDeleted(): bool
    {
        return $this->hash === 'aadd86936a80ee8a369579c3926f1b3c';
    }

    public function getLastModifiedDatetime(): DateTimeInterface
    {
        return new DateTimeImmutable($this->lastModified);
    }
}
