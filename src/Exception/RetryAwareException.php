<?php

namespace App\Exception;

interface RetryAwareException
{
    public function modifyTryNumber(int $tryNumber): int;
    public function modifyDelay(int $delay): int;
}
