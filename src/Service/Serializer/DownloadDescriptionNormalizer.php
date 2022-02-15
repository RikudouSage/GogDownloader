<?php

namespace App\Service\Serializer;

use App\DTO\DownloadDescription;
use App\DTO\MultipleValuesWrapper;
use App\Service\OwnedItemsManager;
use ReflectionProperty;
use RuntimeException;

final class DownloadDescriptionNormalizer implements MultipleValuesNormalizer
{
    public function __construct(
    ) {
    }

    public function normalize(array $value, array $context = []): MultipleValuesWrapper
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
