<?php

namespace App\Presenters;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HouseholdMobilePresenter
{
    public function statusNotesJson(string $mobileStatusKey, ?string $userNotes): string
    {
        return json_encode([
            'report_type' => 'household_status',
            'mobile_status_key' => $mobileStatusKey,
            'mobile_status_label' => $this->mobileStatusLabel($mobileStatusKey, null),
            'user_notes' => $userNotes,
        ], JSON_UNESCAPED_SLASHES);
    }
    public function userProfile($user, ?object $member): ?array
    {
        if (! $user) return null;
        $memberFullName = trim(implode(' ', array_filter([
            $member?->first_name ?? null,
            $member?->middle_name ?? null,
            $member?->last_name ?? null,
        ])));

        return [
            'user_id' => $user->user_id,
            'member_id' => $user->member_id,
            'full_name' => $memberFullName ?: ($member?->name ?? null) ?: ($user->full_name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->username),
            'username' => $user->username,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'role' => [
                'role_key' => $user->role?->role_key,
                'role_name' => $user->role?->role_name,
            ],
        ];
    }

    public function formatHousehold(object $household, int $actualMemberCount = 0): array
    {
        return [
            'household_id' => $household->household_id,
            'household_code' => $household->household_code ?? null,
            'household_name' => $household->household_name ?? 'Household account',
            'family_name' => $this->familyName($household->household_name ?? $household->household_id),
            'contact_number' => $household->contact_number ?? null,
            'emergency_contact' => $household->emergency_contact ?? null,
            'member_count' => $actualMemberCount,
            'address' => $household->full_address ?? 'No address recorded',
            'purok' => $household->purok_sitio ?? 'Not recorded',
            'barangay' => $household->barangay_name ?? 'Not recorded',
            'city' => $household->city_municipality ?? 'Not recorded',
            'province' => $household->province ?? 'Not recorded',
        ];
    }

    public function mobileStatusKey(?string $statusKey): string
    {
        if (in_array($statusKey, ['needs_help', 'need_help', 'needs_assistance'], true)) {
            return 'needs_help';
        }

        if (in_array($statusKey, ['safe', 'active', 'returned'], true)) {
            return 'safe';
        }

        if (in_array($statusKey, ['evacuated', 'relocated'], true)) {
            return 'evacuated';
        }

        if (in_array($statusKey, ['injured', 'missing'], true)) {
            return 'needs_help';
        }

        if (in_array($statusKey, ['unsafe', 'not_evacuated', 'displaced'], true)) {
            return 'unsafe';
        }

        return $statusKey ?: 'unchecked';
    }

    public function mobileStatusLabel(?string $statusKey, ?string $statusLabel): string
    {
        return match ($this->mobileStatusKey($statusKey)) {
            'safe' => 'Safe',
            'evacuated' => 'Evacuated',
            'unsafe' => 'Unsafe',
            'needs_help' => 'Needs help',
            default => $statusLabel ?: 'Unchecked',
        };
    }

    public function displayStatusKey(?string $databaseStatusKey, array $notes = []): string
    {
        $mobileStatusKey = $notes['mobile_status_key'] ?? null;

        if (in_array($mobileStatusKey, ['safe', 'evacuated', 'unsafe', 'needs_help'], true)) {
            return $mobileStatusKey;
        }

        return $this->mobileStatusKey($databaseStatusKey);
    }

    public function displayStatusLabel(string $displayStatusKey, ?string $databaseStatusLabel): string
    {
        return $this->mobileStatusLabel($displayStatusKey, $databaseStatusLabel);
    }

    public function familyName(string $name): string
    {
        $parts = collect(explode(' ', trim($name)))->filter()->values();

        return $parts->last() ?: $name;
    }

    public function personName($name, $firstName, $lastName, string $fallback): string
    {
        $fullName = trim((string) ($name ?: trim(($firstName ?? '') . ' ' . ($lastName ?? ''))));

        return $fullName !== '' ? $fullName : $fallback;
    }

    public function label(?string $value): string
    {
        return Str::of($value ?: '')
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }

    public function dateLabel(?string $value): string
    {
        if (! $value) {
            return 'Not recorded';
        }

        return Carbon::parse($value)->timezone('Asia/Manila')->format('M d, Y g:i A');
    }

    public function qrPayload(object $household, ?array $activeEvent): array
    {
        return [
            'label' => 'Evacuation QR',
            'value' => (string) $household->household_id,
            'household_id' => $household->household_id,
            'household_name' => $household->household_name ?? $household->household_code,
        ];
    }
}


