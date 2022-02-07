<?php

namespace App\DTO;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

final class MultipleValuesWrapper implements IteratorAggregate
{
    private Traversable $iterator;

    public function __construct(
        Traversable|array $iterator,
    ) {
        if (is_array($iterator)) {
            $this->iterator = new ArrayIterator($iterator);
        } else {
            $this->iterator = $iterator;
        }
    }

    public function getIterator(): Traversable
    {
        return $this->iterator;
    }
}
