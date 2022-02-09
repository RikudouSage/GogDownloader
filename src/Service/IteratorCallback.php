<?php

namespace App\Service;

use Iterator;

final class IteratorCallback
{
    public function getIteratorWithCallback(Iterator|array $iterable, callable $callback): Iterator
    {
        foreach ($iterable as $item) {
            yield $callback($item);
        }
    }
}
