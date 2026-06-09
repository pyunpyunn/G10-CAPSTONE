<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    private ProfileService $service;

    public function __construct(ProfileService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request): JsonResponse
    {
        return $this->service->show($request);
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
}