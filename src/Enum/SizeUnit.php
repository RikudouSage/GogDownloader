<?php

namespace App\Enum;

enum SizeUnit: string
{
    case Bytes = 'b';
    case Kilobytes = 'kb';
    case Megabytes = 'mb';
    case Gigabytes = 'gb';
    case Terabytes = 'tb';
}
