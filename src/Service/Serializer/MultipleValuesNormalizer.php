<?php

namespace App\Service\Serializer;

use App\DTO\MultipleValuesWrapper;

interface MultipleValuesNormalizer extends SerializerNormalizer
{
    public function normalize(array $value, array $context = []): MultipleValuesWrapper;
}
