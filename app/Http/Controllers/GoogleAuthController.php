<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shop;
use Google\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles Google OAuth2 authorization flow.
 */
class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google consent screen.
     *
     * An optional `shop` query parameter associates the resulting token with a
     * specific shop rather than the user account.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $client = new Client;
        $client->setAuthConfig(config('google.credentials_path'));
        $client->setRedirectUri(config('google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        foreach (config('google.scopes') as $scope) {
            $client->addScope($scope);
        }

        // Pass the shop ID through Google's state parameter so we can restore it on callback.
        if ($request->filled('shop')) {
            $client->setState((string) $request->integer('shop'));
        }

        return redirect($client->createAuthUrl());
    }

    /**
     * Handle the callback from Google after authorization.
     */
    public function callback(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $client = new Client;
        $client->setAuthConfig(config('google.credentials_path'));
        $client->setRedirectUri(config('google.redirect_uri'));

        $token = $client->fetchAccessTokenWithAuthCode($request->string('code')->toString());

        if (isset($token['error'])) {
            return redirect()->route('shop.edit')
                ->with('error', 'Google authorization failed: '.($token['error_description'] ?? $token['error']));
        }

        $client->setAccessToken($token);
        $refreshToken = $client->getRefreshToken();

        if (! $refreshToken) {
            return redirect()->route('shop.edit')
                ->with('error', 'Google did not return a refresh token. Please try again.');
        }

        $shopId = $request->string('state')->toInteger();
        $shop = $shopId
            ? Shop::query()->where('id', $shopId)->where('user_id', Auth::id())->first()
            : Auth::user()->shops()->first();

        if ($shop) {
            $shop->update(['google_refresh_token' => $refreshToken]);
        } else {
            // No shop yet (onboarding) — store on the user as a temporary holding place.
            Auth::user()->update(['google_refresh_token' => $refreshToken]);
        }

        return redirect()->route('shop.edit')
            ->with('status', 'google-connected');
    }
}
