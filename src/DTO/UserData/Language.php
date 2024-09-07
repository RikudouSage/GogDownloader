<?php

namespace App\DTO\UserData;

final readonly class Language
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
