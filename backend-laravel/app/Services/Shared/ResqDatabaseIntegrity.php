<?php

namespace App\Services\Shared;

use PDO;

/** Additive schema repairs and journaled reference repairs; shared master records are preserved. */
class ResqDatabaseIntegrity
{
    /** Surface references outside the automatic repair plan without choosing destructive lifecycle policies. */
    public function relationshipReview(PDO $pdo, array $relationships): array
    {
        $columns = [];
        foreach ($pdo->query('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME,CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[$column['TABLE_NAME']][$column['COLUMN_NAME']] = $column;
        }
        $foreign = [];
        foreach ($pdo->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $key) {
            $foreign[$key['TABLE_NAME'].'.'.$key['COLUMN_NAME']] = [$key['REFERENCED_TABLE_NAME'], $key['REFERENCED_COLUMN_NAME']];
        }
        $findings = [];
        foreach ($relationships as [$table, $key, $parent, $pk]) {
            $relationship = "$table.$key -> $parent.$pk";
            $enforced = ($foreign["$table.$key"] ?? null) === [$parent, $pk];
            if (! isset($columns[$table][$key], $columns[$parent][$pk])) {
                $findings[] = ['relationship' => $relationship, 'issue' => 'missing_table_or_column'];
                continue;
            }
            $child = $columns[$table][$key];
            $target = $columns[$parent][$pk];
            $comparison = "c.`$key`";
            if ($child['CHARACTER_SET_NAME'] && $child['CHARACTER_SET_NAME'] === $target['CHARACTER_SET_NAME'] && $target['COLLATION_NAME']) {
                $comparison .= ' COLLATE '.$target['COLLATION_NAME'];
            }
            $count = $pdo->query("SELECT COUNT(*) FROM `$table` c LEFT JOIN `$parent` p ON $comparison=p.`$pk` WHERE c.`$key` IS NOT NULL AND p.`$pk` IS NULL")->fetchColumn();
            if ($enforced && ! $count) continue;
            $findings[] = [
                'relationship' => $relationship,
                'issue' => $enforced ? 'orphaned_reference' : (isset($foreign["$table.$key"]) ? 'unexpected_foreign_key_parent' : 'missing_foreign_key'),
                'orphans' => (int) $count,
                'child_type' => $child['COLUMN_TYPE'], 'parent_type' => $target['COLUMN_TYPE'],
                'action' => 'Review live data, nullability and parent deletion workflow before applying a scoped constraint.',
            ];
        }
        return $findings;
    }

    public const SHARED_REFERENCES = [
        ['users', 'role_id', 'roles', 'role_id'],
        ['users', 'household_id', 'households', 'household_id'],
        ['users', 'member_id', 'household_members', 'member_id'],
        ['household_members', 'household_id', 'households', 'household_id'],
        ['households', 'address_id', 'addresses', 'address_id'],
        ['device_tokens', 'household_id', 'households', 'household_id'],
        ['geotagged_locations', 'household_id', 'households', 'household_id'],
        ['resource_requests', 'status_id', 'resource_request_status', 'status_id'],
        ['resource_requests', 'evacuation_center_id', 'evacuation_centers', 'evacuation_center_id'],
    ];

    /** Read-only findings: shared parent records must be reconciled by their authoritative owners. */
    public function sharedReferenceWarnings(PDO $pdo): array
    {
        $columns = [];
        foreach ($pdo->query('SELECT TABLE_NAME,COLUMN_NAME,COLLATION_NAME,CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[$column['TABLE_NAME']][$column['COLUMN_NAME']] = $column;
        }
        $warnings = [];
        foreach (self::SHARED_REFERENCES as [$table, $key, $parent, $parentKey]) {
            if (! isset($columns[$table][$key], $columns[$parent][$parentKey])) continue;
            $comparison = "c.`$key`";
            if ($columns[$table][$key]['CHARACTER_SET_NAME'] && $columns[$parent][$parentKey]['COLLATION_NAME']) {
                $comparison .= ' COLLATE '.$columns[$parent][$parentKey]['COLLATION_NAME'];
            }
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `$table` c LEFT JOIN `$parent` p ON $comparison=p.`$parentKey` WHERE c.`$key` IS NOT NULL AND p.`$parentKey` IS NULL")->fetchColumn();
            if ($count) $warnings[] = ['relationship' => "$table.$key -> $parent.$parentKey", 'orphans' => $count, 'action' => 'Shared records preserved; authoritative reconciliation required.'];
        }
        return $warnings;
    }

    public const PRIMARY_KEYS = [
        'affected_areas' => 'affected_area_id', 'device_tracking_logs' => 'tracking_id',
        'disaster_broadcasts' => 'broadcast_id', 'field_report_categories' => 'category_id',
        'incident_archives' => 'archive_id', 'map_tile_metadata' => 'tile_id',
        'rescue_teams' => 'team_id', 'responder_assignments' => 'assignment_id',
        'responder_check_ins' => 'check_in_id', 'responder_communication_logs' => 'communication_id',
        'responder_field_report_details' => 'detail_id', 'responder_field_reports' => 'report_id',
        'responder_location_logs' => 'log_id', 'responder_routes' => 'route_id',
        'responders' => 'responder_id', 'route_coordinates' => 'coordinate_id',
        'situation_reports' => 'sit_rep_id',
    ];

    public const INDEXES = [
        'responders' => [['user_id'], ['team_id']],
        'responder_assignments' => [['responder_id', 'status'], ['team_id', 'status'], ['disaster_id', 'assigned_at'], ['household_id']],
        'responder_routes' => [['assignment_id'], ['responder_id']],
        'route_coordinates' => [['route_id', 'sequence_order']],
        'responder_location_logs' => [['responder_id', 'logged_at']],
        'responder_communication_logs' => [['responder_id', 'timestamp'], ['team_id', 'timestamp']],
        'responder_check_ins' => [['responder_id', 'checked_in_at'], ['disaster_id', 'household_id']],
        'responder_field_reports' => [['assignment_id'], ['disaster_id', 'household_id'], ['responder_id', 'created_at']],
        'responder_field_report_details' => [['report_id'], ['category_id']],
        'device_tracking_logs' => [['device_token_id', 'logged_at'], ['household_id', 'logged_at']],
        'affected_areas' => [['disaster_id', 'purok_id']],
        'disaster_broadcasts' => [['disaster_id', 'sent_at']],
        'situation_reports' => [['disaster_id', 'generated_at']],
        'incident_archives' => [['disaster_id', 'archive_type'], ['reference_table', 'reference_id']],
        'hq_field_reports' => [['linked_report_id']],
        'request_validations' => [['request_id', 'created_at']],
        // Shared tables: additive indexes used by Resqperation; no record changes.
        'audit_logs' => [['created_at'], ['user_id', 'created_at'], ['reference_table', 'reference_id']],
        'users' => [['email'], ['contact_number'], ['role_id']],
        'disaster_events' => [['ended_at']],
        'personal_access_tokens' => [['tokenable_type', 'tokenable_id']],
    ];

    /** Relationships owned on the child side. Audit/history tables deliberately retain old identifiers. */
    public const RELATIONSHIPS = [
        ['responders', 'user_id', 'users', 'user_id'],
        ['responders', 'team_id', 'rescue_teams', 'team_id'],
        ['rescue_teams', 'leader_responder_id', 'responders', 'responder_id'],
        ['rescue_teams', 'assigned_purok_id', 'puroks', 'purok_id'],
        ['responder_assignments', 'responder_id', 'responders', 'responder_id'],
        ['responder_assignments', 'team_id', 'rescue_teams', 'team_id'],
        ['responder_assignments', 'disaster_id', 'disaster_events', 'event_id'],
        ['responder_assignments', 'household_id', 'households', 'household_id'],
        ['responder_assignments', 'affected_area_id', 'affected_areas', 'affected_area_id'],
        ['responder_routes', 'assignment_id', 'responder_assignments', 'assignment_id'],
        ['responder_routes', 'responder_id', 'responders', 'responder_id'],
        ['route_coordinates', 'route_id', 'responder_routes', 'route_id'],
        ['responder_field_reports', 'responder_id', 'responders', 'responder_id'],
        ['responder_field_reports', 'assignment_id', 'responder_assignments', 'assignment_id'],
        ['responder_field_reports', 'status_log_id', 'household_status_logs', 'status_log_id'],
        ['responder_field_reports', 'disaster_id', 'disaster_events', 'event_id'],
        ['responder_field_reports', 'household_id', 'households', 'household_id'],
        ['responder_field_report_details', 'report_id', 'responder_field_reports', 'report_id'],
        ['responder_field_report_details', 'category_id', 'field_report_categories', 'category_id'],
        ['responder_field_report_members', 'report_id', 'responder_field_reports', 'report_id'],
        ['responder_field_report_members', 'member_id', 'household_members', 'member_id'],
        ['responder_field_report_members', 'household_id', 'households', 'household_id'],
        ['responder_field_report_members', 'disaster_id', 'disaster_events', 'event_id'],
        ['responder_field_report_members', 'status_id', 'member_statuses', 'status_id'],
        ['affected_areas', 'disaster_id', 'disaster_events', 'event_id'],
        ['affected_areas', 'purok_id', 'puroks', 'purok_id'],
        ['affected_areas', 'sitio_id', 'sitios', 'sitio_id'],
        ['affected_areas', 'barangay_id', 'barangays', 'barangay_id'],
        ['affected_areas', 'severity_id', 'severity_levels', 'severity_id'],
        ['disaster_broadcasts', 'disaster_id', 'disaster_events', 'event_id'],
        ['disaster_broadcasts', 'notification_id', 'notifications', 'notif_id'],
        ['disaster_broadcasts', 'weather_log_id', 'weather_logs', 'weather_log_id'],
        ['disaster_broadcasts', 'severity_id', 'severity_levels', 'severity_id'],
        ['situation_reports', 'disaster_id', 'disaster_events', 'event_id'],
        ['situation_reports', 'household_id', 'households', 'household_id'],
        ['household_trusted_pins', 'household_id', 'households', 'household_id'],
        ['trusted_households', 'requesting_household_id', 'households', 'household_id'],
        ['trusted_households', 'trusted_household_id', 'households', 'household_id'],
        ['password_reset_requests', 'user_id', 'users', 'user_id'],
        ['remembered_devices', 'user_id', 'users', 'user_id'],
        ['request_validations', 'request_id', 'resource_requests', 'request_id'],
        ['devices', 'user_id', 'users', 'user_id'],
        ['devices', 'team_id', 'rescue_teams', 'team_id'],
        ['team_bases', 'team_id', 'rescue_teams', 'team_id'],
        ['dispatch_trips', 'device_id', 'devices', 'id'],
        ['sync_events', 'device_id', 'devices', 'id'],
        ['trip_points', 'trip_id', 'dispatch_trips', 'id'],
        ['household_login_accounts', 'household_id', 'households', 'household_id'],
        ['household_login_accounts', 'user_id', 'users', 'user_id'],
        ['household_device_owners', 'household_id', 'households', 'household_id'],
        ['household_device_owners', 'member_id', 'household_members', 'member_id'],
        ['household_password_recovery_otps', 'user_id', 'users', 'user_id'],
        ['household_password_recovery_otps', 'household_id', 'households', 'household_id'],
        ['household_mobile_notifications', 'household_id', 'households', 'household_id'],
        ['household_geographic_classifications', 'household_id', 'households', 'household_id'],
        ['household_geographic_classifications', 'purok_id', 'puroks', 'purok_id'],
        ['household_geographic_classifications', 'sitio_id', 'sitios', 'sitio_id'],
        ['purok_boundaries', 'purok_id', 'puroks', 'purok_id'],
        ['external_report_requests', 'event_id', 'disaster_events', 'event_id'],
        ['situation_report_submissions', 'sit_rep_id', 'situation_reports', 'sit_rep_id'],
        ['hq_field_reports', 'linked_report_id', 'responder_field_reports', 'report_id'],
        ['status_reminders', 'event_id', 'disaster_events', 'event_id'],
        ['status_reminders', 'household_id', 'households', 'household_id'],
        ['status_reminders', 'member_id', 'household_members', 'member_id'],
        ['user_notifications', 'user_id', 'users', 'user_id'],
    ];

    public function plan(PDO $pdo): array
    {
        $columns = [];
        foreach ($pdo->query('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row;
        }
        $indexes = [];
        foreach ($pdo->query('SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY SEQ_IN_INDEX')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexes[$row['TABLE_NAME']][$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
        }
        $foreign = [];
        foreach ($pdo->query('SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $foreign[$row['TABLE_NAME'].'.'.$row['COLUMN_NAME']] = [$row['REFERENCED_TABLE_NAME'], $row['REFERENCED_COLUMN_NAME']];
        }
        $sql = [];
        $blocked = [];
        foreach (self::PRIMARY_KEYS as $table => $key) {
            if (! isset($columns[$table][$key])) {
                $blocked[] = "$table.$key: required table/column is missing";

                continue;
            }
            if (isset($indexes[$table]['PRIMARY'])) {
                if ($indexes[$table]['PRIMARY'] !== [$key]) {
                    $blocked[] = "$table: unexpected primary key; manual review required";
                }

                continue;
            }
            $invalid = $pdo->query("SELECT COUNT(*) FROM (SELECT `$key` FROM `$table` GROUP BY `$key` HAVING `$key` IS NULL OR COUNT(*) > 1) invalid_ids")->fetchColumn();
            if ($invalid) {
                $blocked[] = "$table.$key: duplicate/null IDs";

                continue;
            }
            $sql[] = "ALTER TABLE `$table` ADD PRIMARY KEY (`$key`)";
        }
        foreach (self::INDEXES as $table => $groups) {
            foreach ($groups as $keys) {
                if (array_diff($keys, array_keys($columns[$table] ?? []))) {
                    continue;
                }
                $covered = array_filter($indexes[$table] ?? [], fn ($existing) => array_slice($existing, 0, count($keys)) === $keys);
                if ($covered) {
                    continue;
                }
                $name = 'resq_'.substr(hash('sha256', $table.implode(',', $keys)), 0, 16).'_idx';
                $sql[] = "ALTER TABLE `$table` ADD INDEX `$name` (`".implode('`,`', $keys).'`)';
            }
        }
        if (isset($columns['personal_access_tokens']['token'])) {
            $unique = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='token' AND NON_UNIQUE=0")->fetchColumn();
            if (! $unique) {
                $duplicates = $pdo->query('SELECT COUNT(*) FROM (SELECT token FROM personal_access_tokens GROUP BY token HAVING COUNT(*) > 1) duplicate_tokens')->fetchColumn();
                if ($duplicates) {
                    $blocked[] = 'personal_access_tokens.token: duplicate tokens';
                } else {
                    $sql[] = 'ALTER TABLE `personal_access_tokens` ADD UNIQUE INDEX `resq_pat_token_unique` (`token`)';
                }
            }
        }
        foreach (self::RELATIONSHIPS as [$table, $key, $parent, $pk]) {
            if (isset($foreign[$table.'.'.$key])) {
                if ($foreign[$table.'.'.$key] !== [$parent, $pk]) {
                    $blocked[] = "$table.$key: foreign key points to an unexpected parent";
                }

                continue;
            }
            $child = $columns[$table][$key] ?? null;
            $target = $columns[$parent][$pk] ?? null;
            if (! $child || ! $target) {
                $blocked[] = "$table.$key: missing column/table";

                continue;
            }
            $comparison = "c.`$key`";
            if ($child['CHARACTER_SET_NAME'] && $target['COLLATION_NAME']) {
                $comparison .= ' COLLATE '.$target['COLLATION_NAME'];
            }
            $orphans = (int) $pdo->query("SELECT COUNT(*) FROM `$table` c LEFT JOIN `$parent` p ON $comparison = p.`$pk` WHERE c.`$key` IS NOT NULL AND p.`$pk` IS NULL")->fetchColumn();
            if ($orphans) {
                $blocked[] = "$table.$key -> $parent.$pk: $orphans orphan(s); preserved";

                continue;
            }
            if ($child['COLUMN_TYPE'] !== $target['COLUMN_TYPE'] || $child['COLLATION_NAME'] !== $target['COLLATION_NAME']) {
                // Only align empty Resq-owned columns. Populated columns require individual review.
                $rows = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                $safeRouteId = $table === 'responder_routes' && $key === 'responder_id'
                    && $target['COLUMN_TYPE'] === 'int'
                    && ! $pdo->query('SELECT COUNT(*) FROM responder_routes WHERE responder_id < -2147483648 OR responder_id > 2147483647')->fetchColumn();
                $safeWidening = preg_match('/^varchar\((\d+)\)$/i', $child['COLUMN_TYPE'], $childLength)
                    && preg_match('/^varchar\((\d+)\)$/i', $target['COLUMN_TYPE'], $targetLength)
                    && (int) $targetLength[1] >= (int) $childLength[1];
                $safeAsciiKey = ($child['COLUMN_TYPE'] === $target['COLUMN_TYPE'] || $safeWidening)
                    && $child['CHARACTER_SET_NAME'] === 'utf8mb4'
                    && $target['CHARACTER_SET_NAME'] === 'utf8mb4'
                    && ! $pdo->query("SELECT COUNT(*) FROM `$table` WHERE `$key` REGEXP '[^ -~]'")->fetchColumn();
                if ($rows !== 0 && ! $safeRouteId && ! $safeAsciiKey) {
                    $blocked[] = "$table.$key: incompatible type/collation on populated table";

                    continue;
                }
                $definition = $target['COLUMN_TYPE'];
                if ($target['CHARACTER_SET_NAME']) {
                    $definition .= ' CHARACTER SET '.$target['CHARACTER_SET_NAME'].' COLLATE '.$target['COLLATION_NAME'];
                }
                $definition .= $child['IS_NULLABLE'] === 'YES' ? ' NULL DEFAULT NULL' : ' NOT NULL';
                $sql[] = "ALTER TABLE `$table` MODIFY `$key` $definition";
            }
            $name = 'resq_'.substr(hash('sha256', $table.'.'.$key), 0, 16).'_fk';
            $sql[] = "ALTER TABLE `$table` ADD CONSTRAINT `$name` FOREIGN KEY (`$key`) REFERENCES `$parent` (`$pk`) ON DELETE RESTRICT ON UPDATE RESTRICT";
        }

        return ['sql' => $sql, 'blocked' => $blocked];
    }

    public function apply(PDO $pdo): array
    {
        $pdo->exec('SET SESSION lock_wait_timeout=10');
        $plan = $this->plan($pdo);
        foreach ($plan['sql'] as $sql) {
            $pdo->exec($sql);
        }

        return $plan;
    }

    public function preserveOrphanReferences(PDO $pdo): int
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS resq_orphan_references (
            id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            source_table varchar(64) NOT NULL, source_key varchar(255) NOT NULL,
            source_column varchar(64) NOT NULL, original_value varchar(255) NOT NULL,
            parent_table varchar(64) NOT NULL, preserved_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY resq_orphan_source_unique (source_table, source_key, source_column)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');
        $references = [
            ['responders', 'responder_id', 'user_id', 'users', 'user_id'],
            ['responders', 'responder_id', 'team_id', 'rescue_teams', 'team_id'],
            ['responder_assignments', 'assignment_id', 'disaster_id', 'disaster_events', 'event_id'],
            ['responder_assignments', 'assignment_id', 'household_id', 'households', 'household_id'],
            ['weather_logs', 'weather_log_id', 'disaster_id', 'disaster_events', 'event_id'],
            ['incident_archives', 'archive_id', 'disaster_id', 'disaster_events', 'event_id'],
            ['request_validations', 'validation_id', 'request_id', 'resource_requests', 'request_id'],
        ];
        // Historical validations can outlive an imported request. Keep the record and its original ID.
        $requestColumn = $pdo->query("SELECT COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='request_validations' AND COLUMN_NAME='request_id'")->fetch(PDO::FETCH_ASSOC);
        if (! $requestColumn) {
            throw new \RuntimeException('request_validations.request_id is missing; review the schema before repairing references.');
        }
        if ($requestColumn['IS_NULLABLE'] !== 'YES') {
            $definition = $requestColumn['COLUMN_TYPE'].' CHARACTER SET '.$requestColumn['CHARACTER_SET_NAME'].' COLLATE '.$requestColumn['COLLATION_NAME'].' NULL DEFAULT NULL';
            $pdo->exec('ALTER TABLE request_validations MODIFY request_id '.$definition);
        }
        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($references as [$table, $pk, $key, $parent, $parentKey]) {
                $statement = $pdo->prepare("INSERT IGNORE INTO resq_orphan_references (source_table, source_key, source_column, original_value, parent_table)
                    SELECT ?, CAST(c.`$pk` AS CHAR), ?, CAST(c.`$key` AS CHAR), ? FROM `$table` c
                    LEFT JOIN `$parent` p ON c.`$key` = p.`$parentKey` WHERE c.`$key` IS NOT NULL AND p.`$parentKey` IS NULL");
                $statement->execute([$table, $key, $parent]);
                // Only references safely preserved with their exact original value are cleared.
                $statement = $pdo->prepare("UPDATE `$table` c JOIN resq_orphan_references a ON a.source_table=? AND a.source_key=CAST(c.`$pk` AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_0900_ai_ci AND a.source_column=? AND a.original_value=CAST(c.`$key` AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_0900_ai_ci
                    LEFT JOIN `$parent` p ON c.`$key`=p.`$parentKey` SET c.`$key`=NULL WHERE c.`$key` IS NOT NULL AND p.`$parentKey` IS NULL");
                $statement->execute([$table, $key]);
                $count += $statement->rowCount();
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        return $count;
    }
}
