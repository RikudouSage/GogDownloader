<?php

namespace App\Enum;

enum NamingConvention: string
{
    case Custom = 'custom';
    case GogSlug = 'gog-slug';
    case RomManager = 'rom-manager';
}
