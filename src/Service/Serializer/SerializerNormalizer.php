<?php

namespace App\Service\Serializer;

use App\Service\Serializer;

interface SerializerNormalizer
{
    public function normalize(array $value, array $context, Serializer $serializer): mixed;

    /**
     * @param class-string<object> $class
     */
    public function supports(string $class): bool;
}
