<?php

namespace App\Enum;

enum OperatingSystemNumber: int
{
    case WindowsXP = 2 ** 0;
    case WindowsVista = 2 ** 1;
    case Windows7 = 2 ** 2;
    case Windows8 = 2 ** 3;
    case Windows10 = 2 ** 12;
    case Windows11 = 2 ** 14;
    case OsX10_6 = 2 ** 4;
    case OsX10_7 = 2 ** 5;
    case Ubuntu14 = 2 ** 10;
    case Ubuntu16 = 2 ** 11;
    case Ubuntu18 = 2 ** 13;
    case Ubuntu20 = 2 ** 15;
    case Ubuntu22 = 2 ** 16;
}
