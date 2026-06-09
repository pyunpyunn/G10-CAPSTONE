<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RescuerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescuerAccountController extends Controller
{
    private RescuerAccountService $service;

    public function __construct(RescuerAccountService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function show(int $responderId): JsonResponse
    {
        return $this->service->show($responderId);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function update(Request $request, int $responderId): JsonResponse
    {
        return $this->service->update($request, $responderId);
    }

    public function deactivate(Request $request, int $responderId): JsonResponse
    {
        return $this->service->deactivate($request, $responderId);
    }
}