<?php

namespace App\Exception;

use Exception;
use Throwable;

final class TooManyRetriesException extends Exception
{
    /**
     * @var array<Throwable>
     */
    public readonly array $exceptions;

    public function __construct(array $exceptions, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->exceptions = $exceptions;
    }
}
