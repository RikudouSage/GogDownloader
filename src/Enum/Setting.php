<?php

namespace App\Enum;

use BackedEnum;
use UnitEnum;

enum Setting: string
{
    case DownloadPath = 'download-path';
    case S3StorageClass = 's3-storage-class';
    case NamingConvention = 'naming-convention';

    /**
     * @return callable(string): bool
     */
    public function getValidator(): callable
    {
        /**
         * @param class-string<BackedEnum> $enumClass
         */
        $enum = fn (string $enumClass) => fn (string $value) => $enumClass::tryFrom($value) !== null;

        return match ($this) {
            self::S3StorageClass => $enum(S3StorageClass::class),
            self::NamingConvention => $enum(NamingConvention::class),
            default => fn () => true,
        };
    }

    /**
     * @return array<string>|(callable(): array<string>)|null
     */
    public function getValidValues(): array|null|callable
    {
        /**
         * @param class-string<BackedEnum> $enumClass
         */
        $enum = fn (string $enumClass) => fn () => array_map(fn (object $class) => $class->value, $enumClass::cases());

        return match ($this) {
            self::S3StorageClass => $enum(S3StorageClass::class),
            self::NamingConvention => $enum(NamingConvention::class),
            default => null,
        };
    }
}
