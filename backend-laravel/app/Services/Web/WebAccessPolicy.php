<?php

namespace App\Services\Web;

use App\Models\User;
use Illuminate\Http\Request;

class WebAccessPolicy
{
    private const MODULES = [
        'super_admin' => [
            'dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests',
            'reports_documentation', 'personnel_access', 'profile', 'notifications', 'inquiries',
        ],
        'hq_leader' => [
            'dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests',
            'reports_documentation', 'personnel_access', 'profile', 'notifications',
        ],
        'hq_personnel' => [
            'dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests',
            'reports_documentation', 'profile', 'notifications',
        ],
        'rescuer' => ['dashboard', 'geospatial_analytics', 'profile', 'notifications'],
    ];

    public function modulesFor(User $user): array
    {
        if (! $user->is_active || ($user->roleKey() === 'hq_leader' && ! $user->isHqccLeader())) {
            return [];
        }

        return self::MODULES[$user->roleKey()] ?? [];
    }

    public function allows(?User $user, string $module, string $action = 'view'): bool
    {
        if (! $user || ! $user->is_active || ($user->attributes['deleted_at'] ?? null)) {
            return false;
        }

        $roleKey = $user->roleKey();
        if ($roleKey === 'hq_leader' && ! $user->isHqccLeader()) {
            return false;
        }
        if (! in_array($module, self::MODULES[$roleKey] ?? [], true)) {
            return false;
        }

        return $action !== 'approve' || in_array($roleKey, ['super_admin', 'hq_leader'], true);
    }

    public function moduleForRequest(Request $request): ?string
    {
        $path = trim(preg_replace('#^api/v\d+/#', '', $request->path()), '/');
        $method = strtoupper($request->method());

        if ($path === 'inquiries' || str_starts_with($path, 'archive/inquiry-logs')) return 'inquiries';
        if ($path === 'profile/barangay') return 'personnel_access';
        if ($path === 'profile') return 'profile';
        if (str_starts_with($path, 'notifications')) return 'notifications';
        if (str_starts_with($path, 'rescuers') || str_starts_with($path, 'headquarters-accounts')) return 'personnel_access';
        if ($path === 'global-search') return 'personnel_access';
        if (str_starts_with($path, 'dashboard')) {
            return str_starts_with($path, 'dashboard/active-event') ? 'response_operations' : 'dashboard';
        }
        if (str_starts_with($path, 'resource-requests') || str_starts_with($path, 'resource-request-types')) return 'resources_requests';
        if (str_starts_with($path, 'situation-reports') || str_starts_with($path, 'archive') || str_starts_with($path, 'reports/')) return 'reports_documentation';
        if (preg_match('#^disaster-events/[^/]+/situation-summary$#', $path)) return 'reports_documentation';
        if (str_starts_with($path, 'weather') || str_starts_with($path, 'map/')
            || preg_match('#^disaster-events/[^/]+/weather-logs#', $path)) return 'geospatial_analytics';
        if (str_starts_with($path, 'households')) {
            return $method === 'POST' && preg_match('#/confirm$#', $path)
                ? 'response_operations'
                : 'geospatial_analytics';
        }
        if (str_starts_with($path, 'disaster-events') || str_starts_with($path, 'dispatches')
            || str_starts_with($path, 'field-reports') || $path === 'rescue-teams'
            || str_starts_with($path, 'evacuation') || str_starts_with($path, 'status-types')
            || str_starts_with($path, 'disaster-types')) {
            return 'response_operations';
        }

        return null;
    }

    public function actionForRequest(Request $request): string
    {
        $path = trim(preg_replace('#^api/v\d+/#', '', $request->path()), '/');

        return preg_match('#^resource-requests/[^/]+/(validate|forward|return|complete)$#', $path)
            ? 'approve'
            : 'view';
    }
}