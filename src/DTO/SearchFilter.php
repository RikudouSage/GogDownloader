<?php

namespace App\DTO;

use App\Enum\Language;
use App\Enum\OperatingSystem;

final class SearchFilter
{
    /**
     * @param array<Language>|null $languages
     */
    public function __construct(
        public readonly ?OperatingSystem $operatingSystem = null,
        public readonly ?array $languages = null,
        public readonly ?string $search = null,
        public readonly bool $includeHidden = false,
    ) {
    }
}
