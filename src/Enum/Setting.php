<?php

namespace App\Enum;

enum Setting: string
{
    case DownloadPath = 'download-path';
    case S3StorageClass = 's3-storage-class';
    case NamingConvention = 'naming-convention';
}
