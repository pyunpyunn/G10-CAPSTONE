<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Support\RequestSchema as Schema;
use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HouseholdMemberProfileWorkflow
{
    public function __construct(
        private HouseholdMobileSupport $support,
        private HouseholdMobileReadQuery $readQuery,
        private HouseholdMobilePresenter $presenter,
    ) {}

    public function updateMember(Request $request, string $memberId): JsonResponse
    {
        $user = $request->user();
        $ownerId = $this->support->authenticatedMemberId($user);
        abort_unless($ownerId === $memberId, 403, 'You may edit only your own contact number.');
        if (is_string($request->input('contact_number'))) {
            $contact = preg_replace('/[ ()-]/', '', trim($request->input('contact_number')));
            $request->merge(['contact_number' => $contact === '' ? null : $contact]);
        }
        $validated = $request->validate([
            'contact_number' => ['present', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9]{7,15}$/'],
        ]);
        $unexpected = array_diff(array_keys($request->all()), ['contact_number']);
        if ($unexpected) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'profile' => ['Only your contact number can be edited.'],
            ]);
        }
        DB::transaction(function () use ($request, $user, $memberId, $validated): void {
            DB::table('household_members')->where('member_id', $memberId)
                ->where('household_id', $user->household_id)->update([
                    'contact_number' => $validated['contact_number'], 'updated_at' => now(),
                ]);
            User::query()->whereKey($user->getKey())->update([
                'contact_number' => $validated['contact_number'], 'updated_at' => now(),
            ]);
            $this->support->writeAuditLog($request, 'mobile_update_member_contact', 'household_members', $memberId, $validated);
        });
        $user->refresh();
        return response()->json([
            'message' => 'Contact number saved.',
            'data' => ['user' => $this->presenter->userProfile($user, $this->readQuery->memberForUser($user))],
        ]);
    }

    public function uploadProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $householdId = $this->support->householdId($user);
        abort_unless($householdId, 403, 'This account is not linked to a household record.');
        $memberId = $this->support->authenticatedMemberId($user);
        abort_unless(Schema::hasColumn('users', 'profile_photo'), 503, 'Profile photo storage is not available in the users table.');

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $photo = $validated['photo'];
        $extension = match ($photo->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('The uploaded profile photo format is not supported.'),
        };
        $directory = 'household-profile-photos/'.hash('sha256', $householdId).'/'.hash('sha256', $memberId);
        $path = $photo->storeAs($directory, Str::uuid().'.'.$extension, 'local');
        if (! $path) throw new RuntimeException('The profile photo could not be stored.');

        $previousPath = $user->profile_photo;
        try {
            DB::transaction(function () use ($request, $user, $householdId, $memberId, $path): void {
                $account = DB::table('users')
                    ->where('user_id', $user->user_id)
                    ->where('household_id', $householdId)
                    ->where('member_id', $memberId)
                    ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'));
                $updates = ['profile_photo' => $path];
                if (Schema::hasColumn('users', 'updated_at')) $updates['updated_at'] = now();
                abort_unless($account->update($updates) === 1, 404, 'The household member account was not found.');
                $this->support->writeAuditLog($request, 'mobile_upload_profile_photo', 'users', (string) $user->user_id, [
                    'member_id' => $memberId,
                    'profile_photo' => $path,
                ]);
            });
        } catch (Throwable $error) {
            if (! Storage::disk('local')->delete($path)) {
                report(new RuntimeException('A failed profile-photo update left an unreferenced file at '.$path));
            }
            throw $error;
        }

        if (is_string($previousPath) && $this->isManagedProfilePhotoPath($previousPath)) {
            foreach (['local', 'public'] as $disk) {
                if (! Storage::disk($disk)->delete($previousPath)) {
                    report(new RuntimeException('The replaced profile photo could not be removed from '.$disk.':'.$previousPath));
                }
            }
        }

        $user->refresh();
        return response()->json([
            'message' => 'Profile photo saved.',
            'data' => ['user' => $this->presenter->userProfile($user, $this->readQuery->memberForUser($user))],
        ]);
    }

    private function isManagedProfilePhotoPath(string $path): bool
    {
        return preg_match('~^household-profile-photos/[a-f0-9]{64}/[a-f0-9]{64}/[a-f0-9-]{36}\.(jpg|png|webp)$~i', $path) === 1;
    }
}
