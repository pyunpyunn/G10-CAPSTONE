<?php

namespace App\Services\Shared;

use App\Models\User;
use App\Models\HouseholdMember;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfilePhotoService
{
    private const DISK = 'public';
    private const MAX_KILOBYTES = 5120;

    public function upload(User $user, ?UploadedFile $photo): array
    {
        return $this->uploadForMember($user, $this->memberForUser($user), $photo);
    }

    public function uploadForMember(User $user, HouseholdMember $member, ?UploadedFile $photo): array
    {
        $validated = validator(['photo' => $photo], [
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ])->validate();

        $this->authorizeMember($user, $member);
        $disk = Storage::disk(self::DISK);
        $oldPath = $member->profile_photo;
        $extension = strtolower($validated['photo']->extension());
        $newPath = 'profile-photos/'.(string) $member->getKey().'/'.Str::uuid().'.'.$extension;

        if (! $disk->putFileAs(dirname($newPath), $validated['photo'], basename($newPath))) {
            throw ValidationException::withMessages(['photo' => ['The profile picture could not be stored. Please try again.']]);
        }

        try {
            DB::transaction(function () use ($member, $newPath): void {
                $member->forceFill(['profile_photo' => $newPath])->save();
            });
        } catch (\Throwable $exception) {
            $disk->delete($newPath);
            throw $exception;
        }

        $this->deleteIfUnreferenced($oldPath, (string) $member->getKey());

        return $this->dataForMember($member->fresh());
    }

    public function remove(User $user): array
    {
        return $this->removeForMember($user, $this->memberForUser($user));
    }

    public function removeForMember(User $user, HouseholdMember $member): array
    {
        $this->authorizeMember($user, $member);
        $oldPath = $member->profile_photo;

        DB::transaction(function () use ($member): void {
            $member->forceFill(['profile_photo' => null])->save();
        });

        $this->deleteIfUnreferenced($oldPath, (string) $member->getKey());

        return $this->dataForMember($member->fresh());
    }

    public function data(User $user): array
    {
        return $this->dataForMember($this->memberForUser($user));
    }

    private function dataForMember(HouseholdMember $member): array
    {

        return [
            'profile_photo' => $member->profile_photo,
            'profile_photo_url' => $member->profile_photo
                ? Storage::disk(self::DISK)->url($member->profile_photo)
                : null,
        ];
    }

    private function memberForUser(User $user): HouseholdMember
    {
        $member = $user->relationLoaded('member') ? $user->getRelation('member') : $user->member;

        if (! $member) {
            throw ValidationException::withMessages(['photo' => ['This account is not linked to a household member profile.']]);
        }

        return $member;
    }

    private function authorizeMember(User $user, HouseholdMember $member): void
    {
        $actor = $this->memberForUser($user);

        if ((string) $actor->household_id !== (string) $member->household_id) {
            abort(403, 'You may only manage profile pictures within your household.');
        }

        if (! (bool) $actor->can_report_for_household) {
            throw ValidationException::withMessages(['photo' => ['You do not have permission to change this profile picture.']]);
        }
    }

    private function deleteIfUnreferenced(?string $path, string $currentMemberId): void
    {
        if (! $path || DB::table('household_members')->where('profile_photo', $path)->where('member_id', '<>', $currentMemberId)->exists()) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }
}
