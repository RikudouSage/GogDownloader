<?php

namespace App\Service;

use App\Attribute\ArrayType;
use App\Attribute\SerializedName;
use App\DTO\MultipleValuesWrapper;
use App\DTO\Serializer\Property;
use App\Service\Serializer\SerializerNormalizer;
use Error;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use ReflectionProperty;
use Traversable;

final class Serializer
{
    /**
     * @param iterable<SerializerNormalizer> $normalizers
     */
    public function __construct(
        private readonly iterable $normalizers,
    ) {
    }

    /**
     * @template T
     *
     * @param class-string<T> $targetClass
     *
     * @throws JsonException
     * @throws ReflectionException
     *
     * @return T|MultipleValuesWrapper
     */
    public function deserialize(string|array $json, string $targetClass, array $context = []): object
    {
        if (is_string($json)) {
            $json = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        }

        $object = $this->getObject($targetClass);

        if ($normalizer = $this->findNormalizer($object)) {
            return $normalizer->normalize($json, $context, $this);
        } else {
            foreach ($this->getProperties($object) as $property) {
                $this->setProperty(
                    $property,
                    $object,
                    $json[$property->serializedName] ?? $context[$property->name] ?? null,
                );
            }
        }

        return $object;
    }

    /**
     * @template T
     *
     * @param class-string<T> $targetClass
     *
     * @throws ReflectionException
     *
     * @return T
     */
    private function getObject(string $targetClass): object
    {
        try {
            $object = new $targetClass;
        } catch (Error) {
            $reflection = new ReflectionClass($targetClass);
            $object = $reflection->newInstanceWithoutConstructor();
        }

        return $object;
    }

    /**
     * @param object $object
     *
     * @return iterable<Property>
     */
    private function getProperties(object $object): iterable
    {
        $reflection = new ReflectionObject($object);
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(SerializedName::class);
            $nameAttribute = null;
            if (count($attributes)) {
                $nameAttribute = $attributes[array_key_first($attributes)]->newInstance();
                assert($nameAttribute instanceof SerializedName);
            }
            yield new Property(
                $property->getName(),
                $nameAttribute ? $nameAttribute->name : $property->getName(),
            );
        }
    }

    /**
     * @throws ReflectionException
     */
    private function setProperty(Property $property, object $object, mixed $value): void
    {
        $reflection = new ReflectionProperty($object, $property->name);
        if ($attributes = $reflection->getAttributes(ArrayType::class)) {
            $attribute = $attributes[array_key_first($attributes)]->newInstance();
            assert($attribute instanceof ArrayType);
            if (class_exists($attribute->type)) {
                $result = [];
                foreach ($value as $item) {
                    $deserialized = $this->deserialize($item, $attribute->type);
                    if ($deserialized instanceof MultipleValuesWrapper) {
                        $result = [...$result, ...$deserialized];
                        continue;
                    }
                    if ($deserialized instanceof Traversable) {
                        $deserialized = iterator_to_array($item);
                    }
                    $result[] = $deserialized;
                }
                $value = $result;
            }
        }

        if ($value === null && $reflection->getType() && !$reflection->getType()->allowsNull()) {
            return;
        }
        $reflection->setValue($object, $value);
    }

    private function findNormalizer(object $object): ?SerializerNormalizer
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($object::class)) {
                return $normalizer;
            }
        }

        return null;
    }
}
