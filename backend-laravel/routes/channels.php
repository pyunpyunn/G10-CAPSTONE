<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('operations.{topic}', fn (User $user, string $topic) => in_array($topic,
    ['households', 'mapping', 'dispatch', 'field-reports', 'communications', 'requests', 'weather', 'disasters', 'inquiries', 'accounts'], true)
    && (($topic === 'inquiries' || $topic === 'accounts')
        ? $user->roleKey() === 'operations_admin'
        : in_array($user->roleKey(), ['chair', 'mitigation_prevention', 'preparedness', 'response', 'recovery_rehabilitation', 'rescuer'], true)), ['guards' => ['sanctum']]);

Broadcast::channel('notifications.admin', fn (User $user) => in_array($user->roleKey(), ['chair', 'operations_admin', 'mitigation_prevention', 'preparedness', 'response', 'recovery_rehabilitation', 'rescuer'], true), ['guards' => ['sanctum']]);
Broadcast::channel('users.{id}', fn (User $user, string $id) => (string) $user->getAuthIdentifier() === $id
    && $user->is_active !== false, ['guards' => ['sanctum']]);
