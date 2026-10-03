<?php

namespace App\Services\Web;

use App\Data\InquiryListResult;
use App\Models\AuditLog;
use App\Models\LandingInquiry;
use App\Queries\InquiryListQuery;
use Illuminate\Http\Request;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\Rule;

class InquiryService
{
    private const TABLE = 'landing_inquiries';

    public function store(Request $request): LandingInquiry
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'organization' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:3000'],
        ], [
            'name.required' => 'Name is required before sending an inquiry.',
            'message.required' => 'Message is required before sending an inquiry.',
            'email.email' => 'Enter a valid email address.',
        ]);

        if (! Schema::hasTable(self::TABLE)) {
            abort(503, 'Inquiry storage is not available yet. Please contact the development team directly.');
        }

        $now = now();

        $insert = $this->onlyExistingColumns([
            'name' => trim($validated['name']),
            'organization' => $this->emptyToNull($validated['organization'] ?? null),
            'email' => $this->emptyToNull($validated['email'] ?? null),
            'message' => trim($validated['message']),
            'status' => 'new',
            'source_page' => 'landing_page',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $inquiry = LandingInquiry::query()->create($insert);

        $this->recordAuditLog([
            'user_id' => null,
            'role_key' => 'anonymous',
            'module' => 'inquiry',
            'action' => 'created',
            'reference_table' => 'landing_inquiries',
            'reference_id' => $inquiry->inquiry_id,
            'old_values' => null,
            'new_values' => [
                'status' => 'new',
                'name' => $inquiry->name,
                'organization' => $inquiry->organization,
                'email' => $inquiry->email,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => $now,
        ]);

        return $inquiry;
    }

    public function index(Request $request): InquiryListResult
    {
        if (! Schema::hasTable(self::TABLE)) {
            return new InquiryListResult(null, collect(), app(InquiryListQuery::class)->accountRows(), false);
        }

        $status = strtolower(trim((string) $request->query('status', 'all')));
        $search = trim((string) $request->query('search', ''));
        $perPage = \App\Http\Requests\ListRequest::clampPerPage($request->query('per_page'));
        $query = app(InquiryListQuery::class);
        return new InquiryListResult($query->paginate($status, $search, $perPage),
            $query->statusCounts(), $query->accountRows(), true);
    }

    public function updateStatus(Request $request, int $inquiryId): LandingInquiry
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_review', 'responded', 'closed'])],
        ]);

        if (! Schema::hasTable(self::TABLE)) {
            abort(503, 'Inquiry storage is not available yet.');
        }

        $row = LandingInquiry::query()->whereKey($inquiryId)->first();

        if (! $row) {
            abort(404, 'Inquiry was not found.');
        }

        $oldValues = [
            'status' => $row->status,
            'handled_by_user_id' => $row->handled_by_user_id,
            'responded_at' => $row->responded_at,
        ];

        $update = $this->onlyExistingColumns([
            'status' => $validated['status'],
            'handled_by_user_id' => $request->user()?->user_id,
            'responded_at' => $validated['status'] === 'responded' ? now() : $row->responded_at,
            'updated_at' => now(),
        ]);

        LandingInquiry::query()
            ->whereKey($inquiryId)
            ->update($update);

        $updatedRow = LandingInquiry::query()->whereKey($inquiryId)->first();

        $this->recordAuditLog([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->roleKey(),
            'module' => 'inquiry',
            'action' => 'status_updated',
            'reference_table' => 'landing_inquiries',
            'reference_id' => $inquiryId,
            'old_values' => $oldValues,
            'new_values' => [
                'status' => $updatedRow?->status,
                'handled_by_user_id' => $updatedRow?->handled_by_user_id,
                'responded_at' => $updatedRow?->responded_at,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        return $updatedRow;
    }

    private function onlyExistingColumns(array $data): array
    {
        return collect($data)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn(self::TABLE, $column))
            ->all();
    }

    private function recordAuditLog(array $payload): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $logData = collect($payload)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn('audit_logs', $column) || in_array($column, ['created_at'], true))
            ->all();

        if ($logData === []) {
            return;
        }

        AuditLog::query()->create($logData);
    }

    private function emptyToNull(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}







