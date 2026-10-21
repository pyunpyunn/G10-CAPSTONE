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
        if ($user) $user->refresh();
        $roleKey = $user && method_exists($user, 'roleKey')
            ? $user->roleKey()
            : $user?->role?->role_key;
        $assignedRoleKey = $user && method_exists($user, 'assignedRoleKey')
            ? $user->assignedRoleKey()
            : $roleKey;

        $roleMatches = in_array($roleKey, $roles, true)
            || in_array($assignedRoleKey, array_intersect($roles, ['tl', 'tm']), true);
        if (! $user || ! $roleMatches) {
            return response()->json([
                'message' => 'You are not allowed to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}

