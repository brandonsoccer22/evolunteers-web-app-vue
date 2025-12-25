<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $roles  Comma separated list of role names
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();
        $roleList = collect(explode(',', $roles))
            ->map(fn ($role) => trim($role))
            ->filter()
            ->values()
            ->all();

        if (!$user || empty($roleList) || !$user->hasAnyRole(...$roleList)) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized.');
        }

        return $next($request);
    }
}
