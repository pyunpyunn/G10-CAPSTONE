<?php

namespace App\Services\Web;

use App\Presenters\NotificationPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class NotificationArchive
{
    public const REQUIRED = ['message', 'sent_by', 'evacuation_event_id', 'evacuation_center_id',
        'scheduled_at', 'is_recurring', 'recurrence_type_id', 'recurrence_end_at', 'last_sent_at',
        'created_at', 'channel', 'status', 'target_filter'];

    public function available(): bool
    {
        return Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'source_key');
    }

    public function sync(array $sources, bool $write = true): array
    {
        $report = ['stored' => 0, 'paused' => []];
        if (! $this->available()) return $report;

        $records = [];
        foreach (['broadcasts' => 'broadcast', 'requests' => 'request', 'audit' => 'audit', 'advisories' => 'advisory'] as $group => $prefix) {
            foreach ($sources[$group] ?? [] as $row) {
                $key = match ($group) {
                    'broadcasts' => $row->broadcast_id,
                    'requests' => $row->request_id,
                    'audit' => $row->audit_log_id,
                    default => $row->id,
                };
                $records[$prefix.'-'.$key] = $row instanceof \Illuminate\Database\Eloquent\Model ? $row->getAttributes() : (array) $row;
            }
        }
        foreach (app(NotificationPresenter::class)->feed($sources) as $notice) {
            $source = $records[$notice['id']];
            $values = array_intersect_key($source, array_flip(self::REQUIRED));
            // Only copy known source facts. Disaster event IDs are not evacuation event IDs.
            $values['message'] = $source['message'] ?? $notice['body'];
            $values['sent_by'] = $source['sent_by'] ?? $source['sent_by_admin_id'] ?? $source['user_id'] ?? $source['requested_by'] ?? $source['source'] ?? null;
            $values['created_at'] = $source['created_at'] ?? null;
            $values['source_key'] = $notice['id'];
            $values['feed_payload_json'] = json_encode($notice, JSON_THROW_ON_ERROR);
            $ids = array_values(array_unique([
                ...$this->providerIds($source['push_provider_ids_json'] ?? null),
                ...$this->providerIds($source['onesignal_ids_json'] ?? null),
                ...array_filter([$source['onesignal_id'] ?? null]),
            ]));
            $values['onesignal_id'] = $ids[0] ?? null;
            $values['onesignal_ids_json'] = $ids ? json_encode($ids, JSON_THROW_ON_ERROR) : null;
            $errors = $this->persist($values, $write);
            if ($errors) $report['paused'][] = ['source_key' => $notice['id'], 'missing_or_invalid' => $errors, 'recovered_onesignal_ids' => $ids];
            else $report['stored']++;
        }
        return $report;
    }

    public function persist(array $values, bool $write = true): array
    {
        $rules = array_fill_keys(self::REQUIRED, ['required']);
        foreach (['scheduled_at', 'recurrence_end_at', 'last_sent_at', 'created_at'] as $field) $rules[$field][] = 'date';
        foreach (['sent_by', 'evacuation_event_id', 'evacuation_center_id'] as $field) $rules[$field] = ['required', 'string', 'max:255'];
        foreach (['channel', 'status', 'target_filter'] as $field) $rules[$field] = ['required', 'string', 'max:50'];
        $rules['message'] = ['required', 'string'];
        $rules['is_recurring'] = ['required', 'boolean'];
        $rules['recurrence_type_id'] = ['required', 'integer', 'exists:recurrence_types,type_id'];
        $rules['recurrence_end_at'][] = 'after_or_equal:scheduled_at';
        $rules['source_key'] = ['required', 'string', 'max:120'];
        $rules['onesignal_id'] = ['required', 'uuid'];
        $rules['onesignal_ids_json'] = ['required', 'json'];
        $rules['feed_payload_json'] = ['required', 'json'];
        $validator = Validator::make($values, $rules);
        if ($validator->fails()) return $validator->errors()->toArray();
        $ids = $this->providerIds($values['onesignal_ids_json']);
        if (! in_array($values['onesignal_id'], $ids, true)) return ['onesignal_id' => ['Must match a recovered provider ID.']];
        $values = array_intersect_key($values, $rules);
        if ($write) {
            $existing = DB::table('notifications')->where('source_key', $values['source_key'])->first();
            $changed = ! $existing;
            foreach ($values as $field => $value) {
                if ($existing && (string) $existing->$field !== (string) $value) $changed = true;
            }
            // A feed refresh must not generate another realtime event for an unchanged notice.
            if ($changed) DB::table('notifications')->upsert([$values], ['source_key'], array_diff(array_keys($values), ['source_key']));
        }
        return [];
    }

    public function providerIds(mixed $value): array
    {
        $ids = is_array($value) ? $value : json_decode((string) $value, true);
        return array_values(array_unique(array_filter(is_array($ids) ? $ids : [],
            fn ($id) => is_string($id) && \Illuminate\Support\Str::isUuid($id))));
    }
}
