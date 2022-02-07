<?php

namespace App\DTO\Serializer;

final class Property
{
    public function __construct(
        public readonly string $name,
        public readonly string $serializedName,
    ) {
    }
}
