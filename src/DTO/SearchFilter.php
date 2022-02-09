<?php

namespace App\DTO;

use App\Enum\Language;
use App\Enum\OperatingSystem;

final class SearchFilter
{
    public function __construct(
        public readonly ?OperatingSystem $operatingSystem = null,
        public readonly ?Language $language = null,
        public readonly ?string $search = null,
        public readonly bool $includeHidden = false,
    ) {
    }
}
