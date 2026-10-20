<?php

namespace App\Services\Web;

use App\Models\User;
use Illuminate\Http\Request;

class WebAccessPolicy
{
    public const MODULES = [
        'chair' => ['dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests', 'reports_documentation', 'profile', 'notifications'],
        'operations_admin' => ['personnel_access', 'profile', 'notifications', 'inquiries'],
        'mitigation_prevention' => ['dashboard', 'geospatial_analytics', 'reports_documentation', 'profile', 'notifications'],
        'preparedness' => ['dashboard', 'geospatial_analytics', 'reports_documentation', 'profile', 'notifications'],
        'response' => ['dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests', 'reports_documentation', 'profile', 'notifications'],
        'recovery_rehabilitation' => ['dashboard', 'geospatial_analytics', 'resources_requests', 'reports_documentation', 'profile', 'notifications'],
        'rescuer' => [],
        'household_resident' => [],
    ];

    public function modulesFor(User $user): array
    {
        if (! $user->is_active || ($user->attributes['deleted_at'] ?? null)) {
            return [];
        }

        $roleKey = $user->roleKey();
        $role = $user->relationLoaded('role') ? $user->getRelation('role') : null;
        $allowedModules = self::MODULES[$roleKey] ?? [];
        if ($role?->web_modules !== null) return array_values(array_intersect((array) $role->web_modules, $allowedModules));
        return $allowedModules;
    }

    public function allows(?User $user, string $module, string $action = 'view'): bool
    {
        if (! $user || ! $user->is_active || ($user->attributes['deleted_at'] ?? null)) {
            return false;
        }

        $roleKey = $user->roleKey();
        $modules = $this->modulesFor($user);
        if ($module === 'global_search') {
            return $modules !== [] && $roleKey !== 'operations_admin';
        }
        if (! in_array($module, $modules, true)) {
            return false;
        }

        if ($action === 'manage_hazards' && $module === 'geospatial_analytics') return in_array($roleKey, ['chair', 'mitigation_prevention', 'preparedness'], true);

        if ($action === 'approve' && $module === 'resources_requests') return $roleKey === 'chair';
        if ($action === 'approve' && $module === 'response_operations') return $roleKey === 'chair';
        if ($action === 'publish_alert' && $module === 'response_operations') return $roleKey === 'chair';
        if ($action === 'create_dispatch' && $module === 'response_operations') return in_array($roleKey, ['chair', 'response'], true);
        if ($action !== 'approve') return true;

        $role = $user->relationLoaded('role') ? $user->getRelation('role') : null;
        return $role?->can_approve_requests !== null && (bool) $role->can_approve_requests;
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
        if ($path === 'global-search') return 'global_search';
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
        $method = strtoupper($request->method());

        if (preg_match('#^resource-requests/[^/]+/(validate|forward|return|complete)$#', $path)) return 'approve';
        if (preg_match('#^dispatches(?:/[^/]+)?$#', $path) && $method === 'POST') return 'create_dispatch';
        if (preg_match('#^disaster-events(?:/[^/]+/broadcasts)?$#', $path) && $method === 'POST') return 'publish_alert';
        if (preg_match('#^dispatches/[^/]+/complete$#', $path) && $method === 'POST') return 'approve';

        return 'view';
    }
}