<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $roleKey = $user?->role?->role_key;

        if (! $user || ! in_array($roleKey, $roles, true)) {
            return response()->json([
                'message' => 'You are not allowed to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
