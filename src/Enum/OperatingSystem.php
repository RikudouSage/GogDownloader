<?php

namespace App\Enum;

enum OperatingSystem: string
{
    case Windows = 'windows';
    case MacOS = 'mac';
    case Linux = 'linux';
}
