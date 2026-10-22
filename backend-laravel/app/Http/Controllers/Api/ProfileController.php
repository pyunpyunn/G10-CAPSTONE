<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileWorkspaceResource;
use App\Services\Web\ProfileService;
use App\Services\Shared\ProfilePhotoService;
use App\Models\HouseholdMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    private ProfileService $service;
    private ProfilePhotoService $photoService;

    public function __construct(ProfileService $service, ProfilePhotoService $photoService)
    {
        $this->service = $service;
        $this->photoService = $photoService;
    }

    public function show(Request $request): JsonResponse
    {
        return (new ProfileWorkspaceResource($this->service->show($request)))->response();
    }

    public function update(Request $request): JsonResponse
    {
        return $this->service->update($request);
    }

    public function changePassword(Request $request): JsonResponse
    {
        return $this->service->changePassword($request);
    }

    public function updateBarangay(Request $request): JsonResponse
    {
        return $this->service->updateBarangay($request);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $data = $this->photoService->upload($request->user(), $request->file('photo'));

        return response()->json(['message' => 'Profile picture updated.', 'data' => $data]);
    }

    public function uploadMemberPhoto(Request $request, HouseholdMember $member): JsonResponse
    {
        $data = $this->photoService->uploadForMember($request->user(), $member, $request->file('photo'));

        return response()->json(['message' => 'Profile picture updated.', 'data' => $data]);
    }

    public function removePhoto(Request $request): JsonResponse
    {
        $data = $this->photoService->remove($request->user());

        return response()->json(['message' => 'Profile picture removed.', 'data' => $data]);
    }

    public function removeMemberPhoto(Request $request, HouseholdMember $member): JsonResponse
    {
        $data = $this->photoService->removeForMember($request->user(), $member);

        return response()->json(['message' => 'Profile picture removed.', 'data' => $data]);
    }
}
