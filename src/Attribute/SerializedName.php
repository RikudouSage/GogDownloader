<?php

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class SerializedName
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
