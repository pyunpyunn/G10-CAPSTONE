<?php

namespace App\Services\Web;

use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\BarangayProfile;
use App\Models\User;
use App\Presenters\ProfilePresenter;
use App\Queries\ProfileQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\Rule;

class ProfileWorkflow
{
    public function __construct(private ProfilePresenter $presenter, private ProfileQuery $query) {}

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

        $oldValues = $this->presenter->identity($user);

        $updatedUser = DB::transaction(function () use ($request, $user, $validated, $oldValues): User {
            User::query()->where($user->getKeyName(), $user->getKey())->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'assigned_center_id' => $validated['assigned_center_id'] ?? null,
                'updated_at' => now(),
            ]);
            $updatedUser = $user->fresh()->load('role');
            $this->writeAuditLog($request, 'update_profile', $oldValues, $this->presenter->identity($updatedUser));
            return $updatedUser;
        }, 3);

        return response()->json([
            'message' => 'Profile details updated.',
            'data' => [
                'user' => new UserResource($updatedUser),
                'identity' => $this->presenter->identity($updatedUser),
                'summary' => $this->presenter->summaryCards($request->user(), $this->query->lastSeen($request->user())),
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

        DB::transaction(function () use ($request, $user, $validated): void {
            User::query()->where($user->getKeyName(), $user->getKey())->update([
                'password' => Hash::make($validated['password']),
                'password_changed_at' => now(),
                'must_change_password' => 0,
                'updated_at' => now(),
            ]);
            $this->writeAuditLog($request, 'change_password', null, [
                'user_id' => $user->user_id,
                'changed_at' => now()->toDateTimeString(),
            ]);
        }, 3);

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

        $oldValues = $this->query->barangayProfileData();
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

        DB::transaction(function () use ($request, $validated, $oldValues): void {
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
            $profileId = $validated['profile_id'] ?? BarangayProfile::query()->where('is_active', 1)->value('profile_id');

            BarangayProfile::query()->update(['is_active' => 0]);

            if ($profileId && BarangayProfile::query()->where('profile_id', $profileId)->exists()) {
                BarangayProfile::query()
                    ->where('profile_id', $profileId)
                    ->update($values);
            } else {
                $values = array_intersect_key([
                    ...$values,
                    'created_at' => now(),
                ], array_flip($columns));
                BarangayProfile::query()->create($values);
            }
            $this->writeAuditLog($request, 'update_barangay_profile', $oldValues, $this->query->barangayProfileData());
        }, 3);

        $updatedValues = $this->query->barangayProfileData();

        return response()->json([
            'message' => 'Barangay information saved.',
            'data' => [
                'barangay_profile' => $updatedValues,
            ],
        ]);
    }

    private function writeAuditLog(Request $request, string $action, mixed $oldValues, mixed $newValues): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->roleKey(),
            'module' => 'profile',
            'action' => $action,
            'reference_table' => 'users',
            'reference_id' => $request->user()?->user_id,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}







