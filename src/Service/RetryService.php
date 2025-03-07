<?php

namespace App\Service;

use App\Exception\RetryAwareException;
use App\Exception\TooManyRetriesException;
use Exception;
use Throwable;

final readonly class RetryService
{
    public function __construct(
        private bool $debug,
    ) {
    }

    /**
     * @throws TooManyRetriesException
     * @throws Exception
     */
    public function retry(callable $callable, int $maxRetries, int $retryDelay, ?array $exceptions = null, ?array $ignoreExceptions = null): void
    {
        $retries = 0;
        $thrown = [];
        do {
            try {
                $callable(count($thrown) ? $thrown[array_key_last($thrown)] : null);

                return;
            } catch (Exception $e) {
                $thrown[] = $e;
                ++$retries;
                if (!$this->matches($e, $exceptions)) {
                    throw $e;
                }
                if ($ignoreExceptions && $this->matches($e, $ignoreExceptions)) {
                    throw $e;
                }

                if ($e instanceof RetryAwareException) {
                    $retries = $e->modifyTryNumber($retries);
                    $tempRetryDelay = $e->modifyDelay($retryDelay);
                    if ($tempRetryDelay) {
                        sleep($tempRetryDelay);
                    }
                } else {
                    sleep($retryDelay);
                }
            }
        } while ($retries < $maxRetries);

        if ($this->debug && count($thrown)) {
            throw $thrown[array_key_last($thrown)];
        }

        throw new TooManyRetriesException($thrown, 'The operation has been retried too many times, cancelling');
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
