<?php

namespace App\Enum;

enum S3StorageClass: string
{
    case Standard = 'STANDARD';
    case ReducedRedundancy = 'REDUCED_REDUNDANCY';
    case StandardInfrequentAccess = 'STANDARD_IA';
    case OneZoneInfrequentAccess = 'ONEZONE_IA';
    case IntelligentTiering = 'INTELLIGENT_TIERING';
    case Glacier = 'GLACIER';
    case DeepArchive = 'DEEP_ARCHIVE';
    case Outposts = 'OUTPOSTS';
    case GlacierInstantRetrieval = 'GLACIER_IR';
    case SnowballEdge = 'SNOW';
    case ExpressOneZone = 'EXPRESS_ONEZONE';
}
