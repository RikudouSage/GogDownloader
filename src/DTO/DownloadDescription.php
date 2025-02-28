<?php

namespace App\DTO;

use Stringable;

final class DownloadDescription
{
    public readonly string|Stringable $md5;

    public function __construct(
        public readonly string                 $language,
        public readonly string                 $platform,
        public readonly string                 $name,
        public readonly float                  $size,
        public readonly string                 $url,
        string|Stringable|null                 $md5,
        public readonly string|Stringable|null $gogGameId,
    ) {
        if ($md5 !== null) {
            $this->md5 = $md5;
        }
    }
}
