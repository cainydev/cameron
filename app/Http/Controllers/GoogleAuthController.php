<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Google\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles Google OAuth2 authorization flow.
 */
class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google consent screen.
     */
    public function redirect(): RedirectResponse
    {
        $client = new Client;
        $client->setAuthConfig(config('google.credentials_path'));
        $client->setRedirectUri(config('google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        foreach (config('google.scopes') as $scope) {
            $client->addScope($scope);
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
            return redirect()->route('profile.edit')
                ->with('error', 'Google authorization failed: '.($token['error_description'] ?? $token['error']));
        }

        $client->setAccessToken($token);

        auth()->user()->update([
            'google_refresh_token' => $client->getRefreshToken(),
        ]);

        return redirect()->route('shop.edit')
            ->with('status', 'google-connected');
    }
}
