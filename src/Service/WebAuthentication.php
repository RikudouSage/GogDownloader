<?php

namespace App\Service;

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpFoundation\Request;

final class WebAuthentication
{
    public const AUTH_URL = 'https://auth.gog.com/auth?client_id=46899977096215655&redirect_uri=https%3A%2F%2Fembed.gog.com%2Fon_login_success%3Forigin%3Dclient&response_type=code&layout=client2';

    public function __construct(
        private readonly HttpBrowser $browser,
    ) {
    }

    public function getCode(string $username, string $password): ?string
    {
        $this->browser->request(Request::METHOD_GET, self::AUTH_URL);
        $response = $this->browser->submitForm('login_login', [
            'login[username]' => $username,
            'login[password]' => $password,
        ]);

        $url = $response->getUri();
        if ($url === null) {
            return null;
        }
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query === null) {
            return null;
        }
        parse_str($query, $queryParts);

        return $queryParts['code'] ?? null;
    }
}
