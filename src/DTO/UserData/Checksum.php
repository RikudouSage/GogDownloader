<?php

namespace App\DTO\UserData;

final readonly class Checksum
{
    public function __construct(
        public ?string $cart,
        public ?string $games,
        public ?string $wishlist,
        public mixed $reviewsVotes,
        public mixed $gamesRating,
    ) {
    }
}
