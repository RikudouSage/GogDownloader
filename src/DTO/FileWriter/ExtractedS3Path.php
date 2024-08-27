<?php

namespace App\DTO\FileWriter;

final readonly class ExtractedS3Path
{
    public function __construct(
        public string $bucket,
        public string $key,
    ) {
    }
}
