<?php

namespace App\DTO;

final readonly class OAuthCredentials
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
    ) {
    }
}
