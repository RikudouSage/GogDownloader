<?php

namespace App\DTO\UserData;

use SensitiveParameter;

final readonly class UserData
{
    /**
     * @param array<Currency> $currencies
     */
    public function __construct(
        public string $country,
        public array $currencies,
        public Currency $selectedCurrency,
        public Language $preferredLanguage,
        public string $ratingBrand,
        public bool $isLoggedIn,
        public Checksum $checksum,
        public UpdatesCount $updates,
        public string $userId,
        public string $username,
        public string $galaxyUserId,
        #[SensitiveParameter]
        public string $email,
        public string $avatar,
        public WalletBalance $walletBalance,
        public PurchasedItemsCount $purchasedItems,
        public int $wishlistedItems,
        public array $friends,
        public array $personalizedProductPrices,
        public array $personalizedSeriesPrices,
    ) {
    }
}
