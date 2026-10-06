<?php

namespace App\Presenters;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InquiryPresenter
{
    public function summary(Collection $counts): array
    {
        return [
            'new' => (int) ($counts['new'] ?? 0),
            'in_review' => (int) ($counts['in_review'] ?? 0),
            'responded' => (int) ($counts['responded'] ?? 0),
            'closed' => (int) ($counts['closed'] ?? 0),
        ];
    }

    public function accounts(Collection $rows): array
    {
        $eligible = $rows->filter(fn (User $user): bool => in_array($user->roleKey(), ['super_admin', 'admin', 'rescuer'], true));
        return [
            'summary' => [
                'hq_web' => $eligible->filter(fn (User $user): bool => in_array($user->roleKey(), ['super_admin', 'admin'], true))->count(),
                'rescuer_mobile' => $eligible->filter(fn (User $user): bool => $user->roleKey() === 'rescuer')->count(),
            ],
            'latest' => $eligible->map(fn (User $user): array => [
                'user_id' => $user->user_id, 'username' => $user->username,
                'name' => $user->name ?: $user->username, 'email' => $user->email,
                'role_key' => $user->roleKey(), 'is_active' => (bool) $user->is_active,
            ])->values()->all(),
        ];
    }

    public function present(?object $row): ?array
    {
        if (! $row) {
            return null;
        }

        return [
            'inquiry_id' => (int) $row->inquiry_id,
            'name' => $row->name,
            'organization' => $row->organization,
            'email' => $row->email,
            'message' => $row->message,
            'status' => $row->status ?: 'new',
            'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('M d, Y g:i A') : null,
            'responded_at' => $row->responded_at ? Carbon::parse($row->responded_at)->format('M d, Y g:i A') : null,
        ];
    }
}


