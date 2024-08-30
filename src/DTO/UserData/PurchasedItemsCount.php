<?php

namespace App\DTO\UserData;

final readonly class PurchasedItemsCount
{
    public function __construct(
        public int $games,
        public int $movies,
    ) {
    }
}
