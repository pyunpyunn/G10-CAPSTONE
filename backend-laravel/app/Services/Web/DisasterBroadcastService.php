<?php

namespace App\Services\Web;

use App\Presenters\DisasterBroadcastPresenter;
use App\Queries\DisasterBroadcastQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\ValidationException;

class DisasterBroadcastService
{
    public function __construct(
        private DisasterBroadcastQuery $query,
        private DisasterBroadcastPresenter $presenter,
        private DisasterBroadcastWorkflow $workflow,
    ) {}

    public function index(): JsonResponse
    {
        $event = $this->query->getActiveEvent();
        $eventId = $event?->event_id;
        $broadcasts = $eventId ? $this->query->getBroadcastsForEvent($eventId) : [];

        return response()->json(['data' => $this->query->getWorkspacePayload($event, $broadcasts)]);
    }

    public function show(string $eventId): JsonResponse
    {
        $event = $this->query->findEvent($eventId);
        if (! $event) {
            return response()->json(['message' => 'Disaster event record was not found.'], 404);
        }

        return response()->json(['data' => $this->query->getWorkspacePayload($event, $this->query->getBroadcastsForEvent($eventId))]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        return $this->workflow->storeEvent($request);
    }

    public function updateEvent(Request $request, string $eventId): JsonResponse
    {
        return $this->workflow->updateEvent($request, $eventId);
    }

    public function broadcasts(string $eventId): JsonResponse
    {
        if (! $this->query->findEvent($eventId)) {
            return response()->json(['message' => 'Disaster event record was not found.'], 404);
        }

        return response()->json(['data' => ['broadcasts' => $this->query->getBroadcastsForEvent($eventId)]]);
    }

    public function storeBroadcast(Request $request, string $eventId): JsonResponse
    {
        return $this->workflow->storeBroadcast($request, $eventId);
    }

    public function storeStatusType(Request $request): JsonResponse
    {
        $validated = $request->validate(['label' => ['required', 'string', 'max:16'], 'safety_basis' => ['required', \Illuminate\Validation\Rule::in(['safe', 'unsafe'])]]);
        abort_unless(Schema::hasTable('household_statuses'), 422, 'Household status catalog is unavailable.');

        $labelColumn = Schema::hasColumn('household_statuses', 'status_label')
            ? 'status_label'
            : (Schema::hasColumn('household_statuses', 'status_name') ? 'status_name' : null);
        abort_unless($labelColumn && Schema::hasColumn('household_statuses', 'status_key') && Schema::hasColumn('household_statuses', 'status_id'), 422, 'Household status catalog does not support new options.');

        $label = trim($validated['label']);
        $key = Str::slug($label, '_');
        if ($key === '') {
            throw ValidationException::withMessages(['label' => ['Enter a valid status name.']]);
        }

        DB::transaction(function () use ($label, $labelColumn, $key, $request, $validated): void {
            $duplicate = DB::table('household_statuses')
                ->whereRaw('LOWER('.$labelColumn.') = ?', [Str::lower($label)])
                ->orWhere('status_key', $key)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['label' => ['That status already exists.']]);
            }

            $row = [
                'status_id' => ((int) DB::table('household_statuses')->lockForUpdate()->max('status_id')) + 1,
                'status_key' => $key,
                $labelColumn => $label,
            ];
            if (Schema::hasColumn('household_statuses', 'safety_basis')) $row['safety_basis'] = $validated['safety_basis'];
            if (Schema::hasColumn('household_statuses', 'created_by')) $row['created_by'] = $request->user()?->user_id;
            if (Schema::hasColumn('household_statuses', 'created_at')) $row['created_at'] = now();
            if (Schema::hasColumn('household_statuses', 'updated_at')) $row['updated_at'] = now();
            DB::table('household_statuses')->insert($row);
        });

        return response()->json([
            'message' => 'Household status option saved.',
            'data' => ['status_options' => $this->query->statusOptions()],
        ], 201);
    }

    public function destroyStatusType(string $statusKey): JsonResponse
    {
        if (in_array($statusKey, ['safe', 'unsafe'], true)) {
            throw ValidationException::withMessages(['status_key' => ['Safe and Unsafe are required to classify household reports and cannot be deleted.']]);
        }
        abort_unless(Schema::hasTable('household_statuses'), 422, 'Household status catalog is unavailable.');

        $result = DB::transaction(function () use ($statusKey): string {
            $status = DB::table('household_statuses')->where('status_key', $statusKey)->lockForUpdate()->first();
            if (! $status) return 'not_found';

            $this->deleteCatalogRow('household_statuses', 'status_id', $status->status_id);
            return 'deleted';
        });

        if ($result !== 'deleted') {
            return response()->json(['message' => 'Household status was not found.'], 404);
        }

        return response()->json([
            'message' => 'Household status deleted.',
            'data' => ['status_options' => $this->query->statusOptions()],
        ]);
    }

    public function storeDisasterType(Request $request): JsonResponse
    {
        $rules = ['name' => ['required', 'string', 'max:100']];
        $hasSeverityLevels = $request->exists('severity_levels');
        $hasLegacySeverity = $request->hasAny(['severity_label', 'severity_desc']);
        if ($hasSeverityLevels) {
            $rules['severity_levels'] = ['required', 'array', 'min:1', 'max:10'];
            $rules['severity_levels.*.label'] = ['required', 'string', 'max:20'];
            $rules['severity_levels.*.description'] = ['required', 'string', 'max:20'];
        } elseif ($hasLegacySeverity) {
            $rules['severity_label'] = ['required', 'string', 'max:20'];
            $rules['severity_desc'] = ['required', 'string', 'max:20'];
        }
        $validated = $request->validate($rules);
        $severityEntries = $validated['severity_levels'] ?? (
            $hasLegacySeverity
                ? [['label' => $validated['severity_label'], 'description' => $validated['severity_desc']]]
                : []
        );
        $hasSeverity = count($severityEntries) > 0;
        $severityLabels = array_map(fn (array $level): string => Str::lower(trim($level['label'])), $severityEntries);
        if (count($severityLabels) !== count(array_unique($severityLabels))) {
            throw ValidationException::withMessages(['severity_levels' => ['Severity level labels must be unique.']]);
        }

        abort_unless(Schema::hasTable('disaster_types'), 422, 'Disaster type catalog is unavailable.');
        if ($hasSeverity) {
            abort_unless(
                Schema::hasTable('severity_levels')
                && Schema::hasColumn('severity_levels', 'severity_id')
                && Schema::hasColumn('severity_levels', 'severity_key')
                && Schema::hasColumn('severity_levels', 'severity_label')
                && Schema::hasColumn('severity_levels', 'severity_desc')
                && Schema::hasTable('disaster_type_severity_levels'),
                422,
                'Severity level catalog is unavailable.',
            );
        }

        $name = trim($validated['name']);
        $slug = Str::slug($name, '_');
        if ($slug === '') {
            throw ValidationException::withMessages(['name' => ['Enter a valid disaster type name.']]);
        }

        $type = DB::transaction(function () use ($name, $slug, $validated, $hasSeverity, $severityEntries): array {
            $duplicate = DB::table('disaster_types')->whereRaw('LOWER(type_name) = ?', [Str::lower($name)])->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => ['That disaster type already exists.']]);
            }

            $typeId = ((int) DB::table('disaster_types')->lockForUpdate()->max('type_id')) + 1;
            $code = Str::upper(Str::limit($slug, 20, ''));
            if (DB::table('disaster_types')->where('type_code', $code)->exists()) {
                $suffix = (string) $typeId;
                $code = Str::upper(Str::limit($slug, 19 - strlen($suffix), '').'-'.$suffix);
            }

            $row = ['type_id' => $typeId, 'type_name' => $name];
            if (Schema::hasColumn('disaster_types', 'type_code')) $row['type_code'] = $code;
            if (Schema::hasColumn('disaster_types', 'is_active')) $row['is_active'] = 1;
            if (Schema::hasColumn('disaster_types', 'created_at')) $row['created_at'] = now();
            if (Schema::hasColumn('disaster_types', 'updated_at')) $row['updated_at'] = now();
            DB::table('disaster_types')->insert($row);
            $this->createUnassignedEventTypeAssociation($typeId);

            if ($hasSeverity) {
                foreach ($severityEntries as $index => $level) {
                    $severityId = ((int) DB::table('severity_levels')->lockForUpdate()->max('severity_id')) + 1;
                    $baseKey = Str::limit(Str::slug($code.'_'.$level['label'], '_'), 50, '');
                    $severityKey = $baseKey;
                    $suffix = $typeId.'_'.$index;
                    $attempt = 0;
                    while (DB::table('severity_levels')->where('severity_key', $severityKey)->exists()) {
                        $attempt++;
                        $uniqueSuffix = '_'.$suffix.($attempt > 1 ? '_'.$attempt : '');
                        $severityKey = Str::limit($baseKey, 50 - strlen($uniqueSuffix), '').$uniqueSuffix;
                    }

                    DB::table('severity_levels')->insert([
                        'severity_id' => $severityId,
                        'severity_key' => $severityKey,
                        'severity_label' => trim($level['label']),
                        'severity_desc' => trim($level['description']),
                    ]);
                    DB::table('disaster_type_severity_levels')->insert([
                        'type_id' => $typeId,
                        'severity_id' => $severityId,
                    ]);
                }
            }

            return ['type_id' => $typeId, 'type_name' => $name, 'type_code' => $row['type_code'] ?? null];
        });

        $data = [
            'created_type' => $type,
            'disaster_types' => $this->query->getDisasterTypes(),
        ];
        if ($hasSeverity) {
            $data['severity_levels'] = $this->query->getSeverityLevels();
        }

        return response()->json([
            'message' => 'Disaster type saved.',
            'data' => $data,
        ], 201);
    }

    public function destroyDisasterType(int $typeId): JsonResponse
    {
        abort_unless(Schema::hasTable('disaster_types'), 422, 'Disaster type catalog is unavailable.');

        $result = DB::transaction(function () use ($typeId): string {
            $type = DB::table('disaster_types')->where('type_id', $typeId)->lockForUpdate()->first();
            if (! $type) return 'not_found';

            $severityIds = [];
            if (Schema::hasTable('disaster_type_severity_levels')
                && Schema::hasColumn('disaster_type_severity_levels', 'type_id')
                && Schema::hasColumn('disaster_type_severity_levels', 'severity_id')) {
                $severityIds = DB::table('disaster_type_severity_levels')
                    ->where('type_id', $typeId)
                    ->pluck('severity_id')
                    ->all();
            }

            if (Schema::hasTable('disaster_event_types') && Schema::hasColumn('disaster_event_types', 'type_id')) {
                DB::table('disaster_event_types')->where('type_id', $typeId)->delete();
            }
            if (Schema::hasTable('disaster_type_severity_levels') && Schema::hasColumn('disaster_type_severity_levels', 'type_id')) {
                DB::table('disaster_type_severity_levels')->where('type_id', $typeId)->delete();
            }

            if ($severityIds && Schema::hasTable('severity_levels') && Schema::hasColumn('severity_levels', 'severity_id')) {
                foreach ($severityIds as $severityId) {
                    $isStillAssigned = Schema::hasTable('disaster_type_severity_levels')
                        && Schema::hasColumn('disaster_type_severity_levels', 'severity_id')
                        && DB::table('disaster_type_severity_levels')->where('severity_id', $severityId)->exists();

                    if (! $isStillAssigned) {
                        $this->deleteCatalogRow('severity_levels', 'severity_id', $severityId);
                    }
                }
            }

            $this->deleteCatalogRow('disaster_types', 'type_id', $typeId);
            return 'deleted';
        });

        if ($result !== 'deleted') {
            return response()->json(['message' => 'Disaster type was not found.'], 404);
        }

        return response()->json([
            'message' => 'Disaster type deleted.',
            'data' => ['disaster_types' => $this->query->getDisasterTypes()],
        ]);
    }

    private function createUnassignedEventTypeAssociation(int $typeId): void
    {
        if (! Schema::hasTable('disaster_event_types')
            || ! Schema::hasColumn('disaster_event_types', 'event_type_id')
            || ! Schema::hasColumn('disaster_event_types', 'type_id')) {
            return;
        }

        $row = [
            'event_type_id' => ((int) DB::table('disaster_event_types')->lockForUpdate()->max('event_type_id')) + 1,
            'type_id' => $typeId,
        ];
        if (Schema::hasColumn('disaster_event_types', 'event_id')) $row['event_id'] = null;
        DB::table('disaster_event_types')->insert($row);
    }

    private function deleteCatalogRow(string $table, string $key, mixed $value): void
    {
        $connection = DB::connection();
        $mysql = $connection->getDriverName() === 'mysql';

        if ($mysql) $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table($table)->where($key, $value)->delete();
        } finally {
            if ($mysql) $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}

