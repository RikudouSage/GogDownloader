<?php

namespace App\Service\Serializer;

interface SerializerNormalizer
{
    public function normalize(array $value, array $context = []): mixed;

    /**
     * @param class-string<object> $class
     */
    public function supports(string $class): bool;
}
