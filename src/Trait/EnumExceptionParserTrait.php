<?php

namespace App\Trait;

use ValueError;

trait EnumExceptionParserTrait
{
    private function getInvalidOption(ValueError $error): ?string
    {
        $regex = /** @lang RegExp */ '@^"([^"]+)" is not a valid@';
        if (!preg_match($regex, $error->getMessage(), $matches)) {
            return null;
        }

        return $matches[1];
    }
}
