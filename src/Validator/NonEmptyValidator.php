<?php

namespace App\Validator;

use RuntimeException;

final class NonEmptyValidator
{
    public function __invoke(?string $value): string
    {
        if ($value === null) {
            throw new RuntimeException('This value cannot be empty');
        }

        return $value;
    }
}
