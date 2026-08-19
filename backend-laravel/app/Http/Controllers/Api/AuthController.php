<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private AuthService $service;

    public function __construct(AuthService $service)
    {
        $this->service = $service;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->service->login($request);
    }

    public function me(Request $request): UserResource
    {
        return $this->service->me($request);
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->service->logout($request);
    }

    public function recoveryQuestions(Request $request): JsonResponse
    {
        return $this->service->recoveryQuestions($request);
    }

    public function saveRecoveryQuestions(Request $request): JsonResponse
    {
        return $this->service->saveRecoveryQuestions($request);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return $this->service->resetPassword($request);
    }
}