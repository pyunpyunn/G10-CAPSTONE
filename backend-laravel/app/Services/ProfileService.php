<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Services\BarangayProfileService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ProfileService
{
    private BarangayProfileService $barangayProfile;

    public function __construct(BarangayProfileService $barangayProfile)
    {
        $this->barangayProfile = $barangayProfile;
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'summary' => $this->summaryCards($request),
                'identity' => $this->identity($user),
                'permissions' => $this->permissions($user->role?->role_key),
                'activity' => $this->recentActivity($user->user_id),
                'barangay_profile' => $this->barangayProfileData(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'assigned_center_id' => ['nullable', 'string', 'max:120'],
        ], [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email is already used by another account.',
            'contact_number.max' => 'Mobile number must be shorter.',
        ]);

        $oldValues = $this->identity($user);

        DB::table('users')
            ->where('user_id', $user->user_id)
            ->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'assigned_center_id' => $validated['assigned_center_id'] ?? null,
                'updated_at' => now(),
            ]);

        $updatedUser = $request->user()->fresh()->load('role');
        $this->writeAuditLog($request, 'update_profile', $oldValues, $this->identity($updatedUser));

        return response()->json([
            'message' => 'Profile details updated.',
            'data' => [
                'user' => new UserResource($updatedUser),
                'identity' => $this->identity($updatedUser),
                'summary' => $this->summaryCards($request),
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Current password is required.',
            'password.required' => 'New password is required.',
            'password.min' => 'New password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        DB::table('users')
            ->where('user_id', $user->user_id)
            ->update([
                'password' => Hash::make($validated['password']),
                'must_change_password' => 0,
                'updated_at' => now(),
            ]);

        $this->writeAuditLog($request, 'change_password', null, [
            'user_id' => $user->user_id,
            'changed_at' => now()->toDateTimeString(),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function updateBarangay(Request $request): JsonResponse
    {
        if (! Schema::hasTable('barangay_profiles')) {
            return response()->json([
                'message' => 'Barangay profile settings are not enabled yet. Ask the DB member to apply the approved barangay profile SQL proposal before saving this section.',
            ], 422);
        }

        $oldValues = $this->barangayProfileData();
        $barangayRule = ['nullable', 'integer'];

        if (Schema::hasTable('barangays')) {
            $barangayRule[] = Rule::exists('barangays', 'barangay_id');
        }

        $validated = $request->validate([
            'profile_id' => ['nullable', 'integer'],
            'barangay_id' => $barangayRule,
            'display_name' => ['required', 'string', 'max:150'],
            'city_name' => ['nullable', 'string', 'max:120'],
            'province_name' => ['nullable', 'string', 'max:120'],
            'office_address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'center_latitude' => ['required', 'numeric', 'between:-90,90'],
            'center_longitude' => ['required', 'numeric', 'between:-180,180'],
            'map_zoom' => ['required', 'integer', 'between:12,20'],
            'weather_location_name' => ['nullable', 'string', 'max:150'],
            'weather_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'weather_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'display_name.required' => 'Barangay display name is required.',
            'email.email' => 'Enter a valid barangay email address.',
            'center_latitude.required' => 'Map latitude is required.',
            'center_longitude.required' => 'Map longitude is required.',
            'map_zoom.between' => 'Map zoom must be between 12 and 20.',
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $columns = Schema::getColumnListing('barangay_profiles');
            $values = [
                'barangay_id' => $validated['barangay_id'] ?? null,
                'display_name' => $validated['display_name'],
                'city_name' => $validated['city_name'] ?? null,
                'province_name' => $validated['province_name'] ?? null,
                'office_address' => $validated['office_address'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'email' => $validated['email'] ?? null,
                'center_latitude' => $validated['center_latitude'],
                'center_longitude' => $validated['center_longitude'],
                'map_zoom' => $validated['map_zoom'],
                'weather_location_name' => $validated['weather_location_name'] ?: $validated['display_name'],
                'weather_latitude' => $validated['weather_latitude'] ?? $validated['center_latitude'],
                'weather_longitude' => $validated['weather_longitude'] ?? $validated['center_longitude'],
                'is_active' => 1,
                'configured_by_user_id' => substr((string) $request->user()?->user_id, 0, 50),
                'updated_at' => now(),
            ];
            $values = array_intersect_key($values, array_flip($columns));
            $profileId = $validated['profile_id'] ?? DB::table('barangay_profiles')->where('is_active', 1)->value('profile_id');

            DB::table('barangay_profiles')->update(['is_active' => 0]);

            if ($profileId && DB::table('barangay_profiles')->where('profile_id', $profileId)->exists()) {
                DB::table('barangay_profiles')
                    ->where('profile_id', $profileId)
                    ->update($values);

                return;
            }

            $values = array_intersect_key([
                ...$values,
                'created_at' => now(),
            ], array_flip($columns));

            DB::table('barangay_profiles')->insert($values);
        });

        $updatedValues = $this->barangayProfileData();
        $this->writeAuditLog($request, 'update_barangay_profile', $oldValues, $updatedValues);

        return response()->json([
            'message' => 'Barangay information saved.',
            'data' => [
                'barangay_profile' => $updatedValues,
            ],
        ]);
    }

    private function summaryCards(Request $request): array
    {
        $user = $request->user()->load('role');
        $lastToken = DB::table('personal_access_tokens')
            ->where('tokenable_type', get_class($user))
            ->where('tokenable_id', $user->user_id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->first();
        $lastSeen = $lastToken?->last_used_at ?: $lastToken?->created_at ?: $user->updated_at;

        return [
            [
                'label' => 'Account ID',
                'value' => $user->username ?: $user->user_id,
                'note' => 'Credential role auto-detected',
            ],
            [
                'label' => 'Role',
                'value' => $this->roleShortName($user->role?->role_key),
                'note' => $user->role?->role_name ?: 'HQ/Admin',
            ],
            [
                'label' => 'Access level',
                'value' => $user->role?->role_key === 'super_admin' ? 'Full+' : 'Full',
                'note' => 'Broadcast, SitRep, archive, dispatch',
            ],
            [
                'label' => 'Last login',
                'value' => $lastSeen ? Carbon::parse($lastSeen)->format('g:i A') : 'Current',
                'note' => $lastSeen ? Carbon::parse($lastSeen)->format('M d, Y') : 'Current session',
            ],
        ];
    }

    private function identity(mixed $user): array
    {
        return [
            'account_id' => $user->username ?: $user->user_id,
            'user_id' => $user->user_id,
            'name' => trim($user->first_name.' '.$user->last_name) ?: ($user->name ?: 'HQ/Admin Desk'),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email ?: 'No email recorded',
            'contact_number' => $user->contact_number ?: 'No mobile recorded',
            'status' => $user->is_active ? 'Active and verified' : 'Inactive',
            'assigned_station' => $user->assigned_center_id ?: 'Command desk',
            'role_name' => $user->role?->role_name ?: 'HQ/Admin',
            'role_key' => $user->role?->role_key ?: 'admin',
        ];
    }

    private function permissions(?string $roleKey): array
    {
        $items = [
            [
                'key' => 'broadcast',
                'title' => 'Disaster broadcast control',
                'description' => 'Declare, send, and close official disaster broadcasts.',
            ],
            [
                'key' => 'dispatch',
                'title' => 'Rescue dispatch coordination',
                'description' => 'Open dispatch forms, assign teams, and review field route updates.',
            ],
            [
                'key' => 'sitrep',
                'title' => 'Situation reporting and archive',
                'description' => 'Generate SitReps and view historical disaster event records.',
            ],
            [
                'key' => 'accounts',
                'title' => 'Responder account management',
                'description' => 'Create verified rescuer accounts and maintain team rosters.',
            ],
        ];

        return collect($items)
            ->map(fn (array $item): array => [
                ...$item,
                'status' => in_array($roleKey, ['super_admin', 'admin'], true) ? 'Enabled' : 'Limited',
                'tone' => in_array($roleKey, ['super_admin', 'admin'], true) ? 'green' : 'amber',
            ])
            ->all();
    }

    private function recentActivity(string $userId): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        return DB::table('audit_logs')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->audit_log_id,
                'title' => $this->label($row->module).' '.$this->label($row->action),
                'description' => $this->label($row->reference_table).' #'.$row->reference_id,
                'date_label' => $this->activityDate($row->created_at),
                'time' => Carbon::parse($row->created_at)->format('g:i A'),
            ])
            ->all();
    }

    private function barangayProfileData(): array
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

    private function detectedBarangay(mixed $barangayId, ?string $name): ?array
    {
        if (! Schema::hasTable('barangays')) {
            return null;
        }

        if (! $barangayId && ! $name) {
            return null;
        }

        $query = DB::table('barangays');

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

    private function barangayOptions(): array
    {
        if (! Schema::hasTable('barangays')) {
            return [];
        }

        return DB::table('barangays')
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

    private function activityDate(?string $value): string
    {
        if (! $value) {
            return 'No date';
        }

        $date = Carbon::parse($value);

        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        return $date->format('M d');
    }

    private function roleShortName(?string $roleKey): string
    {
        return $roleKey === 'super_admin' ? 'Super Admin' : 'HQ';
    }

    private function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }

    private function writeAuditLog(Request $request, string $action, mixed $oldValues, mixed $newValues): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'profile',
            'action' => $action,
            'reference_table' => 'users',
            'reference_id' => $request->user()?->user_id,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
