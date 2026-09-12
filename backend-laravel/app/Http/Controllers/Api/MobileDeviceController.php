<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MobileDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDeviceController extends Controller
{
    public function __construct(private MobileDeviceService $service) {}

    public function storePushToken(Request $request): JsonResponse
    {
        return $this->service->storePushToken($request);
    }
}
