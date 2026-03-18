<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shop;
use App\Models\User;
use Google\Ads\GoogleAds\Lib\V20\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V20\GoogleAdsClientBuilder;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Auth\OAuth2;
use Google\Client;
use Google\Service\Webmasters;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Provides authenticated Google API client instances for a given shop or user.
 *
 * Prefers the shop-level refresh token when a shop is provided,
 * falling back to the user-level token for backwards-compatibility.
 */
class GoogleApiService
{
    public function __construct(private Shop|User $context) {}

    /**
     * Build a base Google API client authenticated via the context's refresh token.
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

        $client->fetchAccessTokenWithRefreshToken($this->refreshToken());

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
                'refresh_token' => $this->refreshToken(),
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
            'refresh_token' => $this->refreshToken(),
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
     * Build an authenticated HTTP client pre-configured for the Merchant API v1.
     */
    public function makeMerchantApiClient(): PendingRequest
    {
        $googleClient = $this->makeGoogleClient(['https://www.googleapis.com/auth/content']);
        $token = $googleClient->getAccessToken();

        return Http::withToken($token['access_token'])
            ->baseUrl('https://merchantapi.googleapis.com/');
    }

    /**
     * Resolve the refresh token from the shop (preferred) or user context.
     */
    private function refreshToken(): string
    {
        $token = $this->context instanceof Shop
            ? ($this->context->google_refresh_token ?? $this->context->user?->google_refresh_token)
            : $this->context->google_refresh_token;

        return $token ?? throw new \RuntimeException('No Google refresh token available.');
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
