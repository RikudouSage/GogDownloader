<?php

namespace App\Service\Serializer;

use App\DTO\GameExtra;
use App\DTO\MultipleValuesWrapper;
use App\Service\Serializer;

final readonly class GameExtraNormalizer implements SerializerNormalizer
{
    public function normalize(array $value, array $context, Serializer $serializer): MultipleValuesWrapper|GameExtra
    {
        if (isset($value['total_size'])) {
            $result = [];
            foreach ($value['files'] as $file) {
                $result[] = new GameExtra(
                    id: $value['id'],
                    name: $value['name'],
                    size: $file['size'],
                    url: $file['downlink'],
                    gogGameId: $value['gogGameId'],
                    md5: null,
                );
            }

            return new MultipleValuesWrapper($result);
        }

        return new GameExtra(
            id: $value['extra_id'],
            name: $value['name'],
            size: $value['size'],
            url: $value['url'],
            gogGameId: $value['gog_game_id'],
            md5: $value['md5'],
        );
    }

    public function supports(string $class): bool
    {
        return is_a($class, GameExtra::class, true);
    }
}
