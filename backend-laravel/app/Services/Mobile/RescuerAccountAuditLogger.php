<?php

namespace App\Services\Mobile;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RescuerAccountAuditLogger
{
    public function writeAuditLog(Request $request, string $action, int $responderId, mixed $oldValues, mixed $newValues): void
    {
        if (! DB::getSchemaBuilder()->hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'rescuer_accounts',
            'action' => $action,
            'reference_table' => 'responders',
            'reference_id' => (string) $responderId,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    public function writeTeamAuditLog(Request $request, string $action, int $teamId, mixed $oldValues, mixed $newValues): void
    {
        if (! DB::getSchemaBuilder()->hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'rescuer_accounts',
            'action' => $action,
            'reference_table' => 'rescue_teams',
            'reference_id' => (string) $teamId,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}







