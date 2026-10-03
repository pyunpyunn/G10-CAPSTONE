<?php

namespace App\Http\Resources;

use App\Presenters\InquiryPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryWorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presenter = app(InquiryPresenter::class);
        $result = $this->resource;
        $accounts = $presenter->accounts($result->accounts);
        if (! $result->tableReady) {
            return [
                'summary' => $presenter->summary($result->statusCounts),
                'inquiries' => ['data' => [], 'current_page' => 1, 'per_page' => 10, 'total' => 0],
                'accounts' => $accounts,
                'table_ready' => false,
                'message' => 'landing_inquiries table is not available yet.',
            ];
        }

        $page = $result->inquiries;
        return [
            'summary' => $presenter->summary($result->statusCounts),
            'accounts' => $accounts,
            'inquiries' => [
                'data' => InquiryResource::collection($page->items())->resolve($request),
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'table_ready' => true,
        ];
    }
}
