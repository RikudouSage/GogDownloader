<?php

namespace App\Service;

use App\Exception\TooManyRetriesException;
use Throwable;

final class RetryService
{
    /**
     * @throws TooManyRetriesException
     * @throws Throwable
     */
    public function retry(callable $callable, int $maxRetries, int $retryDelay, ?array $exceptions = null): void
    {
        $retries = 0;
        do {
            try {
                $callable();

                return;
            } catch (Throwable $e) {
                ++$retries;
                if (!$this->matches($e, $exceptions)) {
                    throw $e;
                }
                sleep($retryDelay);
            }
        } while ($retries < $maxRetries);

        throw new TooManyRetriesException('The operation has been retried too many times, cancelling');
    }

    /**
     * @param Throwable $e
     * @param string[]  $exceptions
     *
     * @return bool
     */
    private function matches(Throwable $e, ?array $exceptions): bool
    {
        if ($exceptions === null) {
            return true;
        }

        foreach ($exceptions as $exception) {
            if (is_a($e, $exception)) {
                return true;
            }
        }

        return false;
    }
}
