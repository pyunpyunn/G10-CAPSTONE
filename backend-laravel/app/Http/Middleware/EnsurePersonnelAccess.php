<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePersonnelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canManagePersonnel()) {
            return response()->json(['message' => 'Personnel Access Control is available to Superadmin, Admin, and the HQCC team leader.'], 403);
        }

        return $next($request);
    }
}
