<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePersonnelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) $user->refresh();
        if (! $user?->canManagePersonnel()) {
            return response()->json(['message' => 'Personnel Access Control is available to Super Admin and the selected HQ Leader.'], 403);
        }

        return $next($request);
    }
}
