<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Google\Ads\GoogleAds\Lib\V20\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V20\GoogleAdsClientBuilder;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Auth\OAuth2;
use Google\Client;
use Google\Service\Webmasters;

/**
 * Provides authenticated Google API client instances for a given user.
 */
class GoogleApiService
{
    public function __construct(private User $user) {}

    /**
     * Build a base Google API client authenticated via the user's refresh token.
     *
     * @param  string[]  $scopes
     */
    public function makeGoogleClient(array $scopes = []): Client
    {
        $client = new Client;
        $client->setAuthConfig(config('google.credentials_path'));
        $client->setRedirectUri(config('google.redirect_uri'));
        $client->setScopes($scopes ?: config('google.scopes'));
        $client->setAccessType('offline');

        $client->setAccessToken(['refresh_token' => $this->user->google_refresh_token]);
        $client->fetchAccessTokenWithRefreshToken($this->user->google_refresh_token);

        return $client;
    }

    /**
     * Build an authenticated GA4 Analytics Data client.
     */
    public function makeAnalyticsClient(): BetaAnalyticsDataClient
    {
        $credentials = new UserRefreshCredentials(
            'https://www.googleapis.com/auth/analytics.readonly',
            [
                'client_id' => $this->getOAuthClientId(),
                'client_secret' => $this->getOAuthClientSecret(),
                'refresh_token' => $this->user->google_refresh_token,
            ]
        );

        return new BetaAnalyticsDataClient(['credentials' => $credentials]);
    }

    /**
     * Build an authenticated Google Ads API client.
     */
    public function makeAdsClient(?int $loginCustomerId = null): GoogleAdsClient
    {
        $oAuth2Credential = (new OAuth2([
            'clientId' => $this->getOAuthClientId(),
            'clientSecret' => $this->getOAuthClientSecret(),
            'refresh_token' => $this->user->google_refresh_token,
            'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
        ]));

        $builder = (new GoogleAdsClientBuilder)
            ->withDeveloperToken(config('google.ads_developer_token'))
            ->withOAuth2Credential($oAuth2Credential);

        if ($loginCustomerId !== null) {
            $builder->withLoginCustomerId($loginCustomerId);
        }

        return $builder->build();
    }

    /**
     * Build an authenticated Search Console (Webmasters) service client.
     */
    public function makeSearchConsoleClient(): Webmasters
    {
        $client = $this->makeGoogleClient(['https://www.googleapis.com/auth/webmasters.readonly']);

        return new Webmasters($client);
    }

    /**
     * Read the OAuth2 client ID from the credentials JSON file.
     */
    private function getOAuthClientId(): string
    {
        return $this->readCredentials()['client_id'];
    }

    /**
     * Read the OAuth2 client secret from the credentials JSON file.
     */
    private function getOAuthClientSecret(): string
    {
        return $this->readCredentials()['client_secret'];
    }

    /**
     * Parse the credentials JSON file and return the web/installed credentials.
     *
     * @return array{client_id: string, client_secret: string}
     */
    private function readCredentials(): array
    {
        $path = config('google.credentials_path');
        $contents = json_decode(file_get_contents($path), true);

        return $contents['web'] ?? $contents['installed'];
    }
}
