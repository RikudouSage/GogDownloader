<?php

namespace App\DTO;

final readonly class SaveFileContent
{
    public function __construct(
        public string $hash,
        public string $content,
    ) {
    }
}
