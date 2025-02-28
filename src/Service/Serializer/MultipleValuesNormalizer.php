<?php

namespace App\Service\Serializer;

use App\DTO\MultipleValuesWrapper;
use App\Service\Serializer;

interface MultipleValuesNormalizer extends SerializerNormalizer
{
    public function normalize(array $value, array $context, Serializer $serializer): MultipleValuesWrapper;
}
