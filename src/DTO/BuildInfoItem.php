<?php

namespace App\DTO;

use DateTimeInterface;

final readonly class BuildInfoItem
{
    public string $buildId;
    public string $productId;
    public string $os;
    public ?string $branch;
    public string $versionName;
    /**
     * @var array<string>
     */
    public array $tags;
    public bool $public;
    public DateTimeInterface $datePublished;
    public int $generation;
    public string $link;
}
