<?php

namespace App\Service\Serializer;

use App\DTO\DownloadDescription;
use App\DTO\MultipleValuesWrapper;
use ReflectionProperty;
use RuntimeException;

final class DownloadDescriptionNormalizer implements SerializerNormalizer
{
    public function __construct(
    ) {
    }

    public function normalize(array $value, array $context = []): MultipleValuesWrapper|DownloadDescription
    {
        if (isset($value[0])) {
            $results = [];
            $language = $value[0];
            foreach ($value[1] as $platform => $downloads) {
                foreach ($downloads as $download) {
                    $object = new DownloadDescription();
                    $this->set($object, 'language', $language);
                    $this->set($object, 'platform', $platform);
                    $this->set($object, 'name', $download['name']);
                    $this->set($object, 'size', $this->parseSize($download['size']));
                    $this->set($object, 'url', $download['manualUrl']);

                    $results[] = $object;
                }
            }

            return new MultipleValuesWrapper($results);
        }

        if (isset($value['game_id'])) {
            $object = new DownloadDescription();
            $this->set($object, 'language', $value['language']);
            $this->set($object, 'platform', $value['platform']);
            $this->set($object, 'name', $value['name']);
            $this->set($object, 'size', $value['size']);
            $this->set($object, 'url', $value['url']);
            $this->set($object, 'md5', $value['md5']);

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
