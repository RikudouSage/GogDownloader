<?php

namespace App\DTO;

use DateTimeInterface;

final class Authorization
{
    public function __construct(
        public readonly string $token,
        public readonly string $refreshToken,
        public readonly DateTimeInterface $validUntil,
    ) {
    }

    public function __toString(): string
    {
        return $this->token;
    }
}
