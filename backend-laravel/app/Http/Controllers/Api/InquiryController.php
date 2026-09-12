<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function __construct(private InquiryService $service) {}

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function updateStatus(Request $request, int $inquiryId): JsonResponse
    {
        return $this->service->updateStatus($request, $inquiryId);
    }
}
