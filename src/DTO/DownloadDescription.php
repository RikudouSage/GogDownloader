<?php

namespace App\DTO;

final class DownloadDescription
{
    public readonly string $md5;

    public function __construct(
        public readonly string $language,
        public readonly string $platform,
        public readonly string $name,
        public readonly float  $size,
        public readonly string $url,
        ?string                $md5,
        public ?int            $gogGameId,
    ) {
        if ($md5 !== null) {
            $this->md5 = $md5;
        }
    }
}
