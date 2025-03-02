<?php

namespace App\DTO;

interface DownloadableItem
{
    public string $name {
        get;
    }

    public string $url {
        get;
    }

    public ?string $md5 {
        get;
    }

    public ?int $gogGameId {
        get;
    }
}
