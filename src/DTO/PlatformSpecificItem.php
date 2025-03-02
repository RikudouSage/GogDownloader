<?php

namespace App\DTO;

interface PlatformSpecificItem extends DownloadableItem
{
    public string $platform { get; }
}
