<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfilePhotoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $previousPhoto = $user->profile_photo;
        $photoPath = $validated['photo']->store('profile-photos', 'public');

        if (! $photoPath) {
            return response()->json(['message' => 'Unable to store the profile picture. Please try again.'], 500);
        }

        try {
            DB::transaction(function () use ($user, $photoPath): void {
                $user->forceFill(['profile_photo' => $photoPath])->save();
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($photoPath);
            throw $exception;
        }

        $this->deleteStoredPhoto($previousPhoto);

        return response()->json([
            'message' => 'Profile picture updated.',
            'data' => ['profile_photo' => $photoPath],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $previousPhoto = $user->profile_photo;

        DB::transaction(function () use ($user): void {
            $user->forceFill(['profile_photo' => null])->save();
        });

        $this->deleteStoredPhoto($previousPhoto);

        return response()->json([
            'message' => 'Profile picture removed.',
            'data' => ['profile_photo' => null],
        ]);
    }

    private function deleteStoredPhoto(?string $path): void
    {
        if ($path && str_starts_with($path, 'profile-photos/')) {
            Storage::disk('public')->delete($path);
        }
    }
}