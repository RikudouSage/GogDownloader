<?php

namespace App\Service\Serializer;

use App\DTO\DownloadDescription;
use App\DTO\MultipleValuesWrapper;
use App\Service\Serializer;
use ReflectionProperty;
use RuntimeException;

final class DownloadDescriptionNormalizer implements SerializerNormalizer
{
    public function __construct(
    ) {
    }

    public function normalize(array $value, array $context, Serializer $serializer): MultipleValuesWrapper|DownloadDescription
    {
        if (isset($value[0])) {
            $results = [];
            $language = $value[0];
            foreach ($value[1] as $platform => $downloads) {
                foreach ($downloads as $download) {
                    $object = new DownloadDescription(
                        language: $language,
                        platform: $platform,
                        name: $download['name'],
                        size: $this->parseSize($download['size']),
                        url: $download['manualUrl'],
                        md5: null,
                        gogGameId: $value['gogGameId'] ?? null,
                    );

                    $results[] = $object;
                }
            }

            return new MultipleValuesWrapper($results);
        }

        if (isset($value['game_id'])) {
            $object = new DownloadDescription(
                language: $value['language'],
                platform: $value['platform'],
                name: $value['name'],
                size: $value['size'],
                url: $value['url'],
                md5: $value['md5'],
                gogGameId: $value['gog_game_id'] ?? null,
            );

            return $object;
        }

        throw new RuntimeException('Invalid download description');
    }

    public function supports(string $class): bool
    {
        return $class === DownloadDescription::class;
    }

    private function set(DownloadDescription $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }

    private function parseSize(string $size): float
    {
        $parts = explode(' ', $size);
        $unit = strtolower($parts[1]);
        $coefficient = match ($unit) {
            'kb' => 2**10,
            'mb' => 2**20,
            'gb' => 2**30,
            default => 1,
        };

        return $parts[0] * $coefficient;
    }
}
