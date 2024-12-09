<?php

namespace App\Service;

final class Iterables
{
    public function map(iterable $iterable, callable $callback): iterable
    {
        foreach ($iterable as $item) {
            yield $callback($item);
        }
    }

    public function filter(iterable $iterable, callable $callback): iterable
    {
        foreach ($iterable as $item) {
            if ($callback($item)) {
                yield $item;
            }
        }
    }
}
