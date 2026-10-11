<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Services\Web\InquiryService;
use App\Http\Resources\InquiryWorkspaceResource;
use App\Http\Resources\InquiryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function __construct(private InquiryService $service) {}

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Inquiry sent.',
            'data' => ['inquiry' => new InquiryResource($this->service->store($request))]], 201);
    }

    public function index(ListRequest $request): JsonResponse
    {
        return (new InquiryWorkspaceResource($this->service->index($request)))->response($request);
    }

    public function updateStatus(Request $request, int $inquiryId): JsonResponse
    {
        return response()->json(['message' => 'Inquiry updated.',
            'data' => ['inquiry' => new InquiryResource($this->service->updateStatus($request, $inquiryId))]]);
    }
}



