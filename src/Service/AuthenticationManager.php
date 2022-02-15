<?php

namespace App\Service;

use App\DTO\Authorization;
use App\DTO\Url;
use App\Exception\AuthenticationException;
use App\Service\Persistence\PersistenceManager;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AuthenticationManager
{
    private const AUTH_URL = 'https://auth.gog.com';

    public function __construct(
        private readonly PersistenceManager $persistence,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function codeLogin(string $code): void
    {
        try {
            $response = $this->httpClient->request(
                Request::METHOD_GET,
                new Url(host: self::AUTH_URL, path: 'token', query: [
                    'client_id' => '46899977096215655',
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
