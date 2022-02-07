<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ArrayType
{
    public function __construct(
        public readonly string $type,
    ) {
    }
}
