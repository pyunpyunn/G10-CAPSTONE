<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LandingInquiry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class InquiryService
{
    private const TABLE = 'landing_inquiries';

    public function store(Request $request): JsonResponse
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
            return response()->json([
                'message' => 'Inquiry storage is not available yet. Please contact the development team directly.',
            ], 503);
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

        return response()->json([
            'message' => 'Inquiry sent.',
            'data' => [
                'inquiry' => $this->formatInquiry($inquiry),
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable(self::TABLE)) {
            return response()->json([
                'data' => [
                'summary' => ['new' => 0, 'in_review' => 0, 'responded' => 0, 'closed' => 0],
                'inquiries' => ['data' => [], 'current_page' => 1, 'per_page' => 10, 'total' => 0],
                'accounts' => $this->accountOverview(),
                'table_ready' => false,
                'message' => 'landing_inquiries table is not available yet.',
                ],
            ]);
        }

        $status = strtolower(trim((string) $request->query('status', 'all')));
        $search = trim((string) $request->query('search', ''));
        $perPage = min(50, max(10, (int) $request->query('per_page', 10)));

        $query = LandingInquiry::query()->select($this->landingInquiryColumns());

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('organization', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => [
                'summary' => $this->summary(),
                'accounts' => $this->accountOverview(),
                'inquiries' => [
                    'data' => collect($paginator->items())->map(fn (object $row): array => $this->formatInquiry($row))->values()->all(),
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'table_ready' => true,
            ],
        ]);
    }

    public function updateStatus(Request $request, int $inquiryId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_review', 'responded', 'closed'])],
        ]);

        if (! Schema::hasTable(self::TABLE)) {
            return response()->json(['message' => 'Inquiry storage is not available yet.'], 503);
        }

        $row = LandingInquiry::query()->whereKey($inquiryId)->first();

        if (! $row) {
            return response()->json(['message' => 'Inquiry was not found.'], 404);
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

        return response()->json([
            'message' => 'Inquiry updated.',
            'data' => [
                'inquiry' => $this->formatInquiry($updatedRow),
            ],
        ]);
    }

    private function summary(): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return ['new' => 0, 'in_review' => 0, 'responded' => 0, 'closed' => 0];
        }

        $counts = LandingInquiry::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'new' => (int) ($counts['new'] ?? 0),
            'in_review' => (int) ($counts['in_review'] ?? 0),
            'responded' => (int) ($counts['responded'] ?? 0),
            'closed' => (int) ($counts['closed'] ?? 0),
        ];
    }

    private function accountOverview(): array
    {
        if (! Schema::hasTable('users')) {
            return ['summary' => ['hq_web' => 0, 'rescuer_mobile' => 0], 'latest' => []];
        }

        $hasDeletedAt = Schema::hasColumn('users', 'deleted_at');

        $rows = User::query()
            ->select($this->userOverviewColumns())
            ->with('role')
            ->when($hasDeletedAt, fn ($query) => $query->whereNull('deleted_at'))
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $eligibleRows = $rows->filter(fn (User $user): bool => in_array($user->roleKey(), ['super_admin', 'admin', 'rescuer'], true));

        return [
            'summary' => [
                'hq_web' => $eligibleRows->filter(fn (User $user): bool => in_array($user->roleKey(), ['super_admin', 'admin'], true))->count(),
                'rescuer_mobile' => $eligibleRows->filter(fn (User $user): bool => $user->roleKey() === 'rescuer')->count(),
            ],
            'latest' => $eligibleRows->map(fn (User $user): array => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'name' => $user->name ?: $user->username,
                'email' => $user->email,
                'role_key' => $user->roleKey(),
                'is_active' => (bool) $user->is_active,
            ])->values()->all(),
        ];
    }

    private function formatInquiry(?object $row): ?array
    {
        if (! $row) {
            return null;
        }

        return [
            'inquiry_id' => (int) $row->inquiry_id,
            'name' => $row->name,
            'organization' => $row->organization,
            'email' => $row->email,
            'message' => $row->message,
            'status' => $row->status ?: 'new',
            'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('M d, Y g:i A') : null,
            'responded_at' => $row->responded_at ? Carbon::parse($row->responded_at)->format('M d, Y g:i A') : null,
        ];
    }

    private function landingInquiryColumns(): array
    {
        return [
            'inquiry_id',
            'name',
            'organization',
            'email',
            'message',
            'status',
            'source_page',
            'ip_address',
            'user_agent',
            'responded_at',
            'handled_by_user_id',
            'created_at',
            'updated_at',
        ];
    }

    private function userOverviewColumns(): array
    {
        return [
            'user_id',
            'username',
            'login_id',
            'name',
            'email',
            'role_id',
            'is_active',
            'created_at',
            'updated_at',
        ];
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
