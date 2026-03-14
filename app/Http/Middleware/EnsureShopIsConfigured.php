<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopIsConfigured
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user->hasGoogleConnected()) {
            return redirect()->route('shop.setup');
        }

        if ($user->shops()->doesntExist()) {
            return redirect()->route('shop.edit');
        }

        return $next($request);
    }
}
