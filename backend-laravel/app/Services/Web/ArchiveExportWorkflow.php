<?php

namespace App\Services\Web;

use App\Queries\ArchiveQuery;
use App\Presenters\ArchiveExportPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveExportWorkflow
{
    private const CATEGORIES = ['disaster-events', 'household-status-logs', 'dispatch-logs', 'radio-communication-logs', 'resource-requests', 'situation-reports'];

    public function __construct(private ArchiveQuery $query, private ArchiveExportPresenter $presenter) {}

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $category = (string) $request->query('category', 'disaster-events');
        $type = strtolower((string) $request->query('type', 'csv'));
        if (! in_array($category, self::CATEGORIES, true)) return response()->json(['success' => false, 'message' => 'Select a valid archive category before exporting.'], 422);
        if ($type === 'pdf') return response()->json(['success' => false, 'message' => 'Archive PDF export is reserved for the PDF package step. Use CSV export for now.'], 501);
        if ($type !== 'csv') return response()->json(['success' => false, 'message' => 'Only CSV export is available in this version.'], 422);
        [$total, $records] = $this->query->exportRows($category, $request);
        $headers = $this->presenter->headers($category);
        $filename = 'resqperation-'.$category.'-archive-'.now()->format('Ymd-His').'.csv';
        return response()->streamDownload(function () use ($records, $headers, $category): void {
            $handle = fopen('php://output', 'w');
            try {
                fputcsv($handle, array_values($headers), ',', '"', '');
                foreach ($records as $record) {
                    fputcsv($handle, $this->presenter->row($record, $category), ',', '"', '');
                }
            } finally {
                fclose($handle);
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Archive-Total' => (string) $total]);
    }
}







