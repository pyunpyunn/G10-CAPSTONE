<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('operations.{topic}', fn (User $user, string $topic) => in_array($topic,
    ['households', 'mapping', 'dispatch', 'field-reports', 'communications', 'requests', 'weather', 'disasters', 'inquiries', 'accounts'], true)
    && in_array($user->roleKey(), ['admin', 'super_admin'], true)
    && (! in_array($topic, ['inquiries', 'accounts'], true) || $user->roleKey() === 'super_admin'), ['guards' => ['sanctum']]);

Broadcast::channel('notifications.admin', fn (User $user) => in_array($user->roleKey(), ['admin', 'super_admin'], true), ['guards' => ['sanctum']]);
Broadcast::channel('users.{id}', fn (User $user, string $id) => (string) $user->getAuthIdentifier() === $id
    && in_array($user->roleKey(), ['admin', 'super_admin'], true), ['guards' => ['sanctum']]);
