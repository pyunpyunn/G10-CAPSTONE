<?php

namespace App\Http\Middleware;

use App\Services\Web\WebAccessPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebModuleAccess
{
    public function __construct(private WebAccessPolicy $policy) {}

    public function handle(Request $request, Closure $next, ?string $module = null, string $action = 'view'): Response
    {
        $user = $request->user();
        if ($user) $user->refresh();
        $module ??= $this->policy->moduleForRequest($request);
        $action = $action === 'view' ? $this->policy->actionForRequest($request) : $action;
        if (! $module) {
            return response()->json(['message' => 'No access policy is defined for this operation.'], 403);
        }
        if (! $this->policy->allows($user, $module, $action)) {
            return response()->json(['message' => 'Your role is not allowed to access this operation.'], 403);
        }

        return $next($request);
    }
}