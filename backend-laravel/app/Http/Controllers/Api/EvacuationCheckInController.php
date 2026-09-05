<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EvacuationCheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvacuationCheckInController extends Controller
{
    public function __construct(private EvacuationCheckInService $service) {}

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function verifyQr(Request $request): JsonResponse
    {
        return $this->service->verifyQr($request);
    }
}
