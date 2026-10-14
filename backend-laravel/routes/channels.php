<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('operations.{topic}', fn (User $user, string $topic) => in_array($topic,
    ['households', 'mapping', 'dispatch', 'field-reports', 'communications', 'requests', 'weather', 'disasters', 'inquiries', 'accounts'], true)
    && in_array($user->roleKey(), ['super_admin', 'hq_leader', 'hq_personnel'], true)
    && ($topic !== 'inquiries' || $user->roleKey() === 'super_admin')
    && ($topic !== 'accounts' || in_array($user->roleKey(), ['super_admin', 'hq_leader'], true)), ['guards' => ['sanctum']]);

Broadcast::channel('notifications.admin', fn (User $user) => in_array($user->roleKey(), ['super_admin', 'hq_leader', 'hq_personnel', 'rescuer'], true), ['guards' => ['sanctum']]);
Broadcast::channel('users.{id}', fn (User $user, string $id) => (string) $user->getAuthIdentifier() === $id
    && $user->is_active !== false, ['guards' => ['sanctum']]);
