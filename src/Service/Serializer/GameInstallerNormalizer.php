<?php

namespace App\Service\Serializer;

use App\DTO\GameInstaller;
use App\DTO\MultipleValuesWrapper;
use App\Service\Serializer;
use ReflectionProperty;
use RuntimeException;

final class GameInstallerNormalizer implements SerializerNormalizer
{
    public function __construct(
    ) {
    }

    public function normalize(array $value, array $context, Serializer $serializer): MultipleValuesWrapper|GameInstaller
    {
        if (array_key_exists('version', $value)) {
            $results = [];

            foreach ($value['files'] as $file) {
                $results[] = new GameInstaller(
                    language: $value['language'],
                    platform: $value['os'],
                    name: $value['name'],
                    size: $file['size'],
                    url: $file['downlink'],
                    md5: null,
                    gogGameId: $value['gogGameId'] ?? null,
                );
            }

            return new MultipleValuesWrapper($results);
        }

        if (isset($value['game_id'])) {
            return new GameInstaller(
                language: $value['language'],
                platform: $value['platform'],
                name: $value['name'],
                size: $value['size'],
                url: $value['url'],
                md5: $value['md5'],
                gogGameId: $value['gog_game_id'] ?? null,
            );
        }

        throw new RuntimeException('Invalid download description');
    }

    public function supports(string $class): bool
    {
        return $class === GameInstaller::class;
    }
}
