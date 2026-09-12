<?php

namespace App\Services;

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

        $insert = [
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
        ];

        $inquiryId = DB::table(self::TABLE)->insertGetId($this->onlyExistingColumns($insert));

        return response()->json([
            'message' => 'Inquiry sent.',
            'data' => [
                'inquiry' => $this->formatInquiry(DB::table(self::TABLE)->where('inquiry_id', $inquiryId)->first()),
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

        $query = DB::table(self::TABLE);

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

        $row = DB::table(self::TABLE)->where('inquiry_id', $inquiryId)->first();

        if (! $row) {
            return response()->json(['message' => 'Inquiry was not found.'], 404);
        }

        $update = [
            'status' => $validated['status'],
            'handled_by_user_id' => $request->user()?->user_id,
            'responded_at' => $validated['status'] === 'responded' ? now() : $row->responded_at,
            'updated_at' => now(),
        ];

        DB::table(self::TABLE)
            ->where('inquiry_id', $inquiryId)
            ->update($this->onlyExistingColumns($update));

        return response()->json([
            'message' => 'Inquiry updated.',
            'data' => [
                'inquiry' => $this->formatInquiry(DB::table(self::TABLE)->where('inquiry_id', $inquiryId)->first()),
            ],
        ]);
    }

    private function summary(): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return ['new' => 0, 'in_review' => 0, 'responded' => 0, 'closed' => 0];
        }

        $counts = DB::table(self::TABLE)
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

        $hasRoles = Schema::hasTable('roles') && Schema::hasColumn('users', 'role_id');
        $hasUserRole = Schema::hasColumn('users', 'role');

        if (! $hasRoles && ! $hasUserRole) {
            return ['summary' => ['hq_web' => 0, 'rescuer_mobile' => 0], 'latest' => []];
        }

        $query = DB::table('users as u');

        if ($hasRoles) {
            $query->leftJoin('roles as r', 'r.role_id', '=', 'u.role_id');
        }

        $roleExpression = match (true) {
            $hasRoles && $hasUserRole => 'COALESCE(r.role_key, u.role)',
            $hasRoles => 'r.role_key',
            $hasUserRole => 'u.role',
            default => "''",
        };

        $rows = $query
            ->select([
                'u.user_id',
                'u.username',
                'u.name',
                'u.email',
                'u.is_active',
                'u.created_at',
                DB::raw($roleExpression.' as role_key'),
            ])
            ->where(function ($inner) use ($hasRoles, $hasUserRole): void {
                if ($hasRoles) {
                    $inner->whereIn('r.role_key', ['super_admin', 'admin', 'rescuer']);
                }

                if ($hasUserRole) {
                    $method = $hasRoles ? 'orWhereIn' : 'whereIn';
                    $inner->{$method}('u.role', ['super_admin', 'admin', 'rescuer']);
                }
            })
            ->orderByDesc('u.created_at')
            ->limit(12)
            ->get();

        return [
            'summary' => [
                'hq_web' => $rows->whereIn('role_key', ['super_admin', 'admin'])->count(),
                'rescuer_mobile' => $rows->where('role_key', 'rescuer')->count(),
            ],
            'latest' => $rows->map(fn (object $row): array => [
                'user_id' => $row->user_id,
                'username' => $row->username,
                'name' => $row->name ?: $row->username,
                'email' => $row->email,
                'role_key' => $row->role_key,
                'is_active' => (bool) $row->is_active,
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
            'created_at' => $row->created_at ? date('M d, Y g:i A', strtotime($row->created_at)) : null,
            'responded_at' => $row->responded_at ? date('M d, Y g:i A', strtotime($row->responded_at)) : null,
        ];
    }

    private function onlyExistingColumns(array $data): array
    {
        return collect($data)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn(self::TABLE, $column))
            ->all();
    }

    private function emptyToNull(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
