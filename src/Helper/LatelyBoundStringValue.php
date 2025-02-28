<?php

namespace App\Helper;

use Closure;
use Stringable;

final readonly class LatelyBoundStringValue implements Stringable
{
    /**
     * @var Closure(): string
     */
    private Closure $callback;

    /**
     * @param callable(): string $callback
     */
    public function __construct(
        callable $callback,
    ) {
        $this->callback = $callback(...);
    }

    public function __toString()
    {
        return ($this->callback)();
    }
}
