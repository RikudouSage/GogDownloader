<?php

namespace App\Exception;

use RuntimeException;
use Throwable;

final class ExitException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 1, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
