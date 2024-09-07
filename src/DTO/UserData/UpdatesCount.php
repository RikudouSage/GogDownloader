<?php

namespace App\DTO\UserData;

final readonly class UpdatesCount
{
    public function __construct(
        public int $messages,
        public int $pendingFriendRequests,
        public int $unreadChatMessages,
        public int $products,
        public int $forum,
        public int $total,
    ) {
    }
}
