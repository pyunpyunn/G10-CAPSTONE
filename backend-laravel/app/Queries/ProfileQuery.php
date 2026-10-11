<?php

namespace App\Queries;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Services\Shared\BarangayProfileService;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class ProfileQuery
{
    public function __construct(private BarangayProfileService $barangayProfile) {}

    public function lastSeen(object $user): mixed
    {
        $lastToken = DB::table('personal_access_tokens')->where('tokenable_type', get_class($user))->where('tokenable_id', $user->user_id)->orderByDesc('last_used_at')->orderByDesc('created_at')->first();
        return $lastToken?->last_used_at ?: $lastToken?->created_at ?: $user->updated_at;
    }

    public function recentActivity(string $userId)
    {
        if (! Schema::hasTable('audit_logs')) return collect();
        return AuditLog::query()->where('user_id', $userId)->orderByDesc('created_at')->limit(8)->get();
    }
    public function barangayProfileData(): array
    {
        $profile = $this->barangayProfile->current();
        $detected = $this->detectedBarangay($profile['barangay_id'] ?? null, $profile['name'] ?? null);

        return [
            ...$profile,
            'office_address' => $profile['office_address'] ?? $detected['office_address'] ?? null,
            'contact_number' => $profile['contact_number'] ?? $detected['contact_number'] ?? null,
            'email' => $profile['email'] ?? $detected['email'] ?? null,
            'registered_barangay' => $detected,
            'registered_options' => $this->barangayOptions(),
            'can_save' => Schema::hasTable('barangay_profiles'),
            'save_note' => Schema::hasTable('barangay_profiles')
                ? 'Barangay information can be edited and saved for this RESQPERATION deployment.'
                : 'Barangay data is detected from SafeTrack/shared records or environment fallback. Saving needs the approved barangay_profiles table.',
        ];
    }

    public function detectedBarangay(mixed $barangayId, ?string $name): ?array
    {
        if (! Schema::hasTable('barangays')) {
            return null;
        }

        if (! $barangayId && ! $name) {
            return null;
        }

        $query = Barangay::query();

        if ($barangayId) {
            $query->where('barangay_id', $barangayId);
        } elseif ($name) {
            $search = str_replace('Barangay ', '', $name);
            $query->where('barangay_name', 'like', '%'.$search.'%');
        }

        $row = $query->first();

        if (! $row) {
            return null;
        }

        return [
            'barangay_id' => $row->barangay_id ?? null,
            'barangay_code' => $row->barangay_code ?? null,
            'barangay_name' => $row->barangay_name ?? null,
            'city_name' => $row->city_name ?? null,
            'province_name' => $row->province_name ?? null,
            'office_address' => $row->office_address ?? null,
            'contact_number' => $row->contact_number ?? null,
            'email' => $row->email ?? null,
            'source' => 'SafeTrack/shared barangays table',
        ];
    }

    public function barangayOptions(): array
    {
        if (! Schema::hasTable('barangays')) {
            return [];
        }

        return Barangay::query()
            ->orderBy('barangay_name')
            ->limit(80)
            ->get()
            ->map(fn (object $row): array => [
                'barangay_id' => $row->barangay_id ?? null,
                'label' => $row->barangay_name ?? 'Barangay',
                'barangay_code' => $row->barangay_code ?? null,
                'city_name' => $row->city_name ?? null,
                'province_name' => $row->province_name ?? null,
            ])
            ->all();
    }
}



