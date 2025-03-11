<?php

namespace App\Exception;

use RuntimeException;

final class ForceRetryException extends RuntimeException implements RetryAwareException
{
    public function modifyTryNumber(int $tryNumber): int
    {
        return $tryNumber - 1;
    }

    public function modifyDelay(int $delay): int
    {
        return 0;
    }
}
