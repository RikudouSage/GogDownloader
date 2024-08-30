<?php

namespace App\DTO\UserData;

use SensitiveParameter;

final readonly class WalletBalance
{
    public function __construct(
        public string $currency,
        #[SensitiveParameter]
        public int $amount,
    ) {
    }
}
