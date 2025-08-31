<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class SlidingApiTokenExpiration
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->expires_at) {
                // Check if expired
                if (now()->greaterThan($accessToken->expires_at)) {
                    abort(401, 'Token expired');
                }
                // Update expiration (e.g., extend by 2 hours)
                $accessToken->expires_at = now()->addHours(2);
                $accessToken->save();

                $user = $accessToken->tokenable; // Get the related user (or model)
                Auth::login($user); // Log in the user for the current request
                return $next($request);
            }
        }
        abort(401, 'Unauthorized');
    }

}
