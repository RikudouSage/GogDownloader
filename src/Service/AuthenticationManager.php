<?php

namespace App\Service;

use App\DTO\Authorization;
use App\DTO\Url;
use App\DTO\UserData\Checksum;
use App\DTO\UserData\Currency;
use App\DTO\UserData\Language;
use App\DTO\UserData\PurchasedItemsCount;
use App\DTO\UserData\UpdatesCount;
use App\DTO\UserData\UserData;
use App\DTO\UserData\WalletBalance;
use App\Exception\AuthenticationException;
use App\Service\Persistence\PersistenceManager;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AuthenticationManager
{
    private const AUTH_URL = 'https://auth.gog.com';

    public function __construct(
        private PersistenceManager  $persistence,
        private HttpClientInterface $httpClient,
        private string              $clientId,
    ) {
    }

    public function codeLogin(string $code): void
    {
        try {
            $response = $this->httpClient->request(
                Request::METHOD_GET,
                new Url(host: self::AUTH_URL, path: 'token', query: [
                    'client_id' => $this->clientId,
                    'client_secret' => '9d85c43b1482497dbbce61f6e4aa173a433796eeae2ca8c5f6129f2dc4de46d9',
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => 'https://embed.gog.com/on_login_success?origin=client',
                ])
            );

            $json = json_decode($response->getContent(), true);

            $result = new Authorization(
                $json['access_token'],
                $json['refresh_token'],
                (new DateTimeImmutable())->add(new DateInterval("PT{$json['expires_in']}S")),
            );

            $this->persistence->saveAuthorization($result);
        } catch (ClientExceptionInterface | ServerExceptionInterface) {
            throw new AuthenticationException('Failed to log in using the code, please try again.');
        }
    }

    public function getAuthorization(): Authorization
    {
        $authorization = $this->persistence->getAuthorization();
        if ($authorization === null) {
            throw new AuthenticationException('No authorization data are stored. Please login before using this command.');
        }
        $now = new DateTimeImmutable();

        if ($now > $authorization->validUntil) {
            $authorization = $this->refreshToken($authorization);
        }

        return $authorization;
    }

    public function getUserInfo(): UserData
    {
        $response = $this->httpClient->request(Request::METHOD_GET, 'https://embed.gog.com/userData.json', [
            'auth_bearer' => (string) $this->getAuthorization(),
        ]);
        $json = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // todo update serializer
        return new UserData(
            country: $json['country'],
            currencies: array_map(
                fn (array $currency) => new Currency(
                    code: $currency['code'],
                    symbol: $currency['symbol'],
                ),
                $json['currencies'],
            ),
            selectedCurrency: new Currency(
                code: $json['selectedCurrency']['code'],
                symbol: $json['selectedCurrency']['symbol'],
            ),
            preferredLanguage: new Language(
                code: $json['preferredLanguage']['code'],
                name: $json['preferredLanguage']['name'],
            ),
            ratingBrand: $json['ratingBrand'],
            isLoggedIn: $json['isLoggedIn'],
            checksum: new Checksum(
                cart: $json['checksum']['cart'],
                games: $json['checksum']['games'],
                wishlist: $json['checksum']['wishlist'],
                reviewsVotes: $json['checksum']['reviews_votes'],
                gamesRating: $json['checksum']['games_rating'],
            ),
            updates: new UpdatesCount(
                messages: $json['updates']['messages'],
                pendingFriendRequests: $json['updates']['pendingFriendRequests'],
                unreadChatMessages: $json['updates']['unreadChatMessages'],
                products: $json['updates']['products'],
                forum: $json['updates']['forum'],
                total: $json['updates']['total'],
            ),
            userId: $json['userId'],
            username: $json['username'],
            galaxyUserId: $json['galaxyUserId'],
            email: $json['email'],
            avatar: $json['avatar'],
            walletBalance: new WalletBalance(
                currency: $json['walletBalance']['currency'],
                amount: $json['walletBalance']['amount'],
            ),
            purchasedItems: new PurchasedItemsCount(
                games: $json['purchasedItems']['games'],
                movies: $json['purchasedItems']['movies'],
            ),
            wishlistedItems: $json['wishlistedItems'],
            friends: $json['friends'],
            personalizedProductPrices: $json['personalizedProductPrices'],
            personalizedSeriesPrices: $json['personalizedSeriesPrices'],
        );
    }

    public function getGameAuthorization(string $clientId, string $clientSecret): Authorization
    {
        $json = json_decode($this->httpClient->request(
            Request::METHOD_GET,
            "https://auth.gog.com/token?client_id={$clientId}&client_secret={$clientSecret}&grant_type=refresh_token&refresh_token={$this->getAuthorization()->refreshToken}&without_new_session=1"
        )->getContent(), true);

        return new Authorization(
            $json['access_token'],
            $json['refresh_token'],
            (new DateTimeImmutable())->add(new DateInterval("PT{$json['expires_in']}S")),
        );
    }

    private function refreshToken(Authorization $authorization): Authorization
    {
        try {
            $response = $this->httpClient->request(
                Request::METHOD_GET,
                new Url(host: self::AUTH_URL, path: 'token', query: [
                    'client_id' => '46899977096215655',
                    'client_secret' => '9d85c43b1482497dbbce61f6e4aa173a433796eeae2ca8c5f6129f2dc4de46d9',
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $authorization->refreshToken,
                    'redirect_uri' => 'https://embed.gog.com/on_login_success?origin=client',
                ])
            );

            $json = json_decode($response->getContent(), true);

            $result = new Authorization(
                $json['access_token'],
                $json['refresh_token'],
                (new DateTimeImmutable())->add(new DateInterval("PT{$json['expires_in']}S")),
            );

            $this->persistence->saveAuthorization($result);

            return $result;
        } catch (ClientExceptionInterface | ServerExceptionInterface) {
            throw new AuthenticationException('Failed to refresh authorization data, please login again.');
        }
    }
}
