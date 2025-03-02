<?php

namespace App\Helper;

use Closure;
use Stringable;

final class LatelyBoundStringValue implements Stringable
{
    private ?string $value = null;

    /**
     * @var Closure(): string
     */
    private readonly Closure $callback;

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
        $this->value ??= ($this->callback)();

        return $this->value;
    }
}
