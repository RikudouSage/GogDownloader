<?php

namespace App\DTO\UserData;

final readonly class Currency
{
    public function __construct(
        public string $code,
        public string $symbol,
    ) {
    }
}
