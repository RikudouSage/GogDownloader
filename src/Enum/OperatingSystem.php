<?php

namespace App\Enum;

enum OperatingSystem: string
{
    case Windows = 'windows';
    case MacOS = 'mac';
    case Linux = 'linux';
    public function getAsNumbers(): string
    {
        $cases = match ($this) {
            self::MacOS => [OperatingSystemNumber::OsX10_6, OperatingSystemNumber::OsX10_7],
            self::Windows => [
                OperatingSystemNumber::WindowsXP,
                OperatingSystemNumber::WindowsVista,
                OperatingSystemNumber::Windows7,
                OperatingSystemNumber::Windows8,
                OperatingSystemNumber::Windows10,
                OperatingSystemNumber::Windows11,
            ],
            self::Linux => [
                OperatingSystemNumber::Ubuntu14,
                OperatingSystemNumber::Ubuntu16,
                OperatingSystemNumber::Ubuntu18,
            ],
        };

        return implode(',', array_map(fn (OperatingSystemNumber $number) => $number->value, $cases));
    }
}
