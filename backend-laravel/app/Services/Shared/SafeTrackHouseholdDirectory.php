<?php

namespace App\Services\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SafeTrackHouseholdDirectory
{
    private const PATH = 'integrations/safetrack-households.json';

    public function saved(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::PATH)) return ['records' => [], 'synced_at' => null];
        $saved = json_decode($disk->get(self::PATH), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($saved) || ! is_array($saved['records'] ?? null)) throw new RuntimeException('Saved SafeTrack data is invalid.');
        return $saved;
    }

    public function changes(array $records): array
    {
        $previous = collect($this->saved()['records'])->keyBy('household_id');
        $new = 0;
        $updated = 0;
        foreach ($records as $record) {
            $id = $record['household_id'] ?? null;
            if (! is_scalar($id) || (string) $id === '') throw new RuntimeException('SafeTrack returned a household without an ID.');
            if (! $previous->has($id)) $new++;
            elseif ($previous->get($id) != $record) $updated++;
        }
        return ['new_households' => $new, 'updated_households' => $updated,
            'removed_households' => $previous->keys()->diff(collect($records)->pluck('household_id'))->count()];
    }

    public function sync(array $records): array
    {
        $changes = $this->changes($records);
        if (collect($records)->pluck('household_id')->unique()->count() !== count($records)) {
            throw new RuntimeException('SafeTrack returned duplicate household IDs.');
        }
        $saved = ['records' => $records, 'synced_at' => now()->toIso8601String()];
        if (! Storage::disk('local')->put(self::PATH, json_encode($saved, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('SafeTrack household data could not be saved.');
        }
        return array_merge($changes, ['synced_at' => $saved['synced_at'], 'total' => count($records)]);
    }

    public function page(Request $request): array
    {
        $saved = $this->saved();
        $records = collect($saved['records']);
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        if ($search !== '') $records = $records->filter(fn ($record) => str_contains(mb_strtolower(json_encode($record)), $search));
        $records = $records->values();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $total = $records->count();
        $items = $records->slice(($page - 1) * $perPage, $perPage)->map(function (array $record): array {
            $location = $record['location'] ?? [];
            $members = is_array($record['members'] ?? null) ? $record['members'] : [];
            return [
                'household' => array_merge(['member_count' => null], $record),
                'address' => $location ?: null,
                'geographic_status' => 'source_record',
                'member_count' => $record['member_count'] ?? count($members),
                'account_count' => 0, 'members' => $members, 'accounts' => [],
            ];
        })->values()->all();
        return ['source' => 'SafeTrack', 'synced_at' => $saved['synced_at'], 'summary' => null,
            'households' => ['data' => $items, 'meta' => ['current_page' => $page, 'per_page' => $perPage,
                'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage)),
                'from' => count($items) ? ($page - 1) * $perPage + 1 : null,
                'to' => count($items) ? ($page - 1) * $perPage + count($items) : null]]];
    }
}
