<?php

namespace App\DTO;

final class DownloadDescription
{
    public readonly string $language;

    public readonly string $platform;

    public readonly string $name;

    public readonly float $size;

    public readonly string $url;

    public readonly string $md5;
}
