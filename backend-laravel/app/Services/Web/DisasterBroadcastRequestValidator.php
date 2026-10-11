<?php

namespace App\Services\Web;

use App\Presenters\DisasterBroadcastPresenter;
use App\Queries\DisasterBroadcastQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisasterBroadcastRequestValidator
{
    public function __construct(private DisasterBroadcastQuery $query, private DisasterBroadcastPresenter $presenter) {}

    public function validateEvent(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type_id' => ['required', 'integer', 'exists:disaster_types,type_id'],
            'severity_level_id' => ['required', 'integer', 'exists:severity_levels,severity_id'],
            'started_at' => ['nullable', 'date'],
        ]);
    }

    public function validateEventUpdate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type_id' => ['required', 'integer', 'exists:disaster_types,type_id'],
            'severity_level_id' => ['required', 'integer', 'exists:severity_levels,severity_id'],
        ]);
    }

    public function validateBroadcast(Request $request): array
    {
        $validated = $request->validate([
            'broadcast_title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'severity_id' => ['nullable', 'integer', 'exists:severity_levels,severity_id'],
            'scope_type' => ['required', 'string', Rule::in(['barangay_wide', 'selected_puroks'])],
            'target_area' => ['nullable', 'string', 'max:150'],
            'estimated_duration' => ['nullable', 'string', 'max:50'],
            'channel' => ['nullable', 'string', 'max:50'],
            'allowed_statuses' => ['required', 'array', 'size:4'],
            'allowed_statuses.*' => ['required', 'string', 'max:40'],
            'direct_puroks' => ['nullable', 'array', 'max:5'],
            'direct_puroks.*.name' => ['required_with:direct_puroks', 'string', 'max:80'],
            'direct_puroks.*.priority' => ['nullable', 'string', Rule::in(['critical', 'high', 'watch', 'monitor'])],
        ]);

        $statusKeys = array_values($validated['allowed_statuses']);

        if (count(array_unique($statusKeys)) !== 4) {
            throw ValidationException::withMessages([
                'allowed_statuses' => ['Select four different household mobile status buttons.'],
            ]);
        }

        if ($validated['scope_type'] === 'selected_puroks' && empty($validated['direct_puroks'])) {
            throw ValidationException::withMessages([
                'direct_puroks' => ['Select at least one directly affected purok for this broadcast scope.'],
            ]);
        }

        if ($validated['scope_type'] === 'selected_puroks') {
            if (! Schema::hasTable('households')
                || ! Schema::hasColumn('households', 'address_id')
                || ! Schema::hasTable('addresses')
                || ! Schema::hasColumn('addresses', 'purok_sitio')) {
                throw ValidationException::withMessages([
                    'direct_puroks' => ['Household purok locations are unavailable. Select Barangay-wide or contact the system administrator.'],
                ]);
            }

            $selectedNames = collect($validated['direct_puroks'])
                ->map(fn (array $purok): string => trim($purok['name']))
                ->unique()
                ->values();
            $knownNameQuery = DB::table('households as h')
                ->join('addresses as a', 'a.address_id', '=', 'h.address_id')
                ->whereIn('a.purok_sitio', $selectedNames)
                ->whereNotNull('a.purok_sitio')
                ->where('a.purok_sitio', '<>', '');

            if (Schema::hasColumn('households', 'deleted_at')) {
                $knownNameQuery->whereNull('h.deleted_at');
            }

            if (Schema::hasColumn('addresses', 'deleted_at')) {
                $knownNameQuery->whereNull('a.deleted_at');
            }

            $knownNames = $knownNameQuery->distinct()->pluck('a.purok_sitio');

            if ($knownNames->count() !== $selectedNames->count()) {
                throw ValidationException::withMessages([
                    'direct_puroks' => ['One or more selected puroks are not in the database. Refresh and select them again.'],
                ]);
            }

            $validated['direct_puroks'] = $selectedNames
                ->map(fn (string $name): array => ['name' => $name])
                ->all();
        }

        return $validated;
    }
}







