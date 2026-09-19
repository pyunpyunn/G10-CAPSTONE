-- RESQPERATION schema upgrade (safe additive version)
-- Source: database_schema.sql (original/live shape)
-- Target reference: new_schema.sql
-- MySQL 8.0+ / tested design for MySQL 9.5
--
-- This migration is forward-only, additive, and data-preserving:
--   * adds missing tables, columns, indexes, foreign keys, and views;
--   * preserves legacy columns used by existing Laravel/mobile code;
--   * preserves existing primary keys and lookup IDs;
--   * does not DROP TABLE, DROP COLUMN, or reset data;
--   * preserves the existing member-status rollup triggers/procedures.
--   * preserves every original household-member demographic column; this
--     migration only appends the three new operational member columns.
--   * uses restrictive foreign keys by default; it does not introduce
--     cascading deletes into the original database.
--   * leaves FOREIGN_KEY_CHECKS enabled; bad legacy references stop the
--     migration before a foreign key is added.
--
-- Review before production use. Back up first:
--   mysqldump -uroot -p resq_local > backup_before_schema_upgrade.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
SET SQL_SAFE_UPDATES = 0;

-- ---------------------------------------------------------------------
-- Small idempotent helpers. They are removed at the end of the script.
-- ---------------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_upgrade_add_column;
DROP PROCEDURE IF EXISTS sp_upgrade_add_index;
DROP PROCEDURE IF EXISTS sp_upgrade_add_fk;
DROP PROCEDURE IF EXISTS sp_upgrade_repair_pk;
DROP PROCEDURE IF EXISTS sp_upgrade_assert_unique;
DROP PROCEDURE IF EXISTS sp_upgrade_assert_key;
DROP PROCEDURE IF EXISTS sp_upgrade_assert_fk_clean;
DROP PROCEDURE IF EXISTS sp_upgrade_assert_composite_unique;

DELIMITER $$

CREATE PROCEDURE sp_upgrade_add_column(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE sp_upgrade_add_index(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE sp_upgrade_add_fk(
    IN p_table VARCHAR(64),
    IN p_constraint VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND CONSTRAINT_NAME = p_constraint
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_constraint, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE sp_upgrade_repair_pk(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = 'PRIMARY'
    ) THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', p_table, '` MODIFY `', p_column,
            '` INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`', p_column, '`)' 
        );
    ELSE
        SET @sql = CONCAT(
            'ALTER TABLE `', p_table, '` MODIFY `', p_column,
            '` INT NOT NULL AUTO_INCREMENT'
        );
    END IF;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

CREATE PROCEDURE sp_upgrade_assert_unique(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_description VARCHAR(255)
)
BEGIN
    SET @duplicate_count = 0;
    SET @sql = CONCAT(
        'SELECT COUNT(*) INTO @duplicate_count FROM (',
        'SELECT `', p_column, '` FROM `', p_table, '` ',
        'WHERE `', p_column, '` IS NOT NULL ',
        'GROUP BY `', p_column, '` HAVING COUNT(*) > 1',
        ') duplicate_rows'
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    IF @duplicate_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = p_description;
    END IF;
END$$

CREATE PROCEDURE sp_upgrade_assert_key(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_description VARCHAR(255)
)
BEGIN
    SET @invalid_key_count = 0;
    SET @sql = CONCAT(
        'SELECT COUNT(*) INTO @invalid_key_count FROM `', p_table, '` ',
        'WHERE `', p_column, '` IS NULL'
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    IF @invalid_key_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = p_description;
    END IF;

    CALL sp_upgrade_assert_unique(p_table, p_column, p_description);
END$$

CREATE PROCEDURE sp_upgrade_assert_fk_clean(
    IN p_child_table VARCHAR(64),
    IN p_child_column VARCHAR(64),
    IN p_parent_table VARCHAR(64),
    IN p_parent_column VARCHAR(64),
    IN p_description VARCHAR(255)
)
BEGIN
    SET @orphan_count = 0;
    SET @sql = CONCAT(
        'SELECT COUNT(*) INTO @orphan_count FROM `', p_child_table, '` child ',
        'LEFT JOIN `', p_parent_table, '` parent ',
        'ON parent.`', p_parent_column, '` = child.`', p_child_column, '` ',
        'WHERE child.`', p_child_column, '` IS NOT NULL ',
        'AND parent.`', p_parent_column, '` IS NULL'
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    IF @orphan_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = p_description;
    END IF;
END$$

CREATE PROCEDURE sp_upgrade_assert_composite_unique(
    IN p_table VARCHAR(64),
    IN p_first_column VARCHAR(64),
    IN p_second_column VARCHAR(64),
    IN p_description VARCHAR(255)
)
BEGIN
    SET @duplicate_count = 0;
    SET @sql = CONCAT(
        'SELECT COUNT(*) INTO @duplicate_count FROM (',
        'SELECT `', p_first_column, '`, `', p_second_column, '` ',
        'FROM `', p_table, '` ',
        'GROUP BY `', p_first_column, '`, `', p_second_column, '` ',
        'HAVING COUNT(*) > 1',
        ') duplicate_rows'
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    IF @duplicate_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = p_description;
    END IF;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 1. Existing tables: columns from new_schema.sql
-- ---------------------------------------------------------------------

CALL sp_upgrade_add_column('relationships', 'is_head',
    'TINYINT(1) NOT NULL DEFAULT 0 AFTER `relationship_label`');
CALL sp_upgrade_add_column('relationships', 'is_immediate_family',
    'TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_head`');
CALL sp_upgrade_add_column('relationships', 'can_report_for_household',
    'TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_immediate_family`');
CALL sp_upgrade_add_column('relationships', 'sort_order',
    'SMALLINT UNSIGNED NOT NULL DEFAULT 999 AFTER `can_report_for_household`');
CALL sp_upgrade_add_column('relationships', 'is_active',
    'TINYINT(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL sp_upgrade_add_column('relationships', 'created_at',
    'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
CALL sp_upgrade_add_column('relationships', 'updated_at',
    'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL sp_upgrade_add_column('household_members', 'is_household_head',
    'TINYINT(1) NOT NULL DEFAULT 0 AFTER `relationship_id`');
CALL sp_upgrade_add_column('household_members', 'can_report_for_household',
    'TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_household_head`');
CALL sp_upgrade_add_column('household_members', 'contact_number',
    'VARCHAR(50) DEFAULT NULL AFTER `can_report_for_household`');

CALL sp_upgrade_add_column('users', 'member_id',
    'VARCHAR(255) DEFAULT NULL AFTER `household_id`');

-- The original schema has no users.member_id. The column is added above
-- before this check, so the check is valid on the original database.
CALL sp_upgrade_assert_unique(
    'users',
    'member_id',
    'Migration stopped: duplicate non-null users.member_id values must be resolved before adding uk_users_member.'
);

CALL sp_upgrade_add_column('household_statuses', 'severity_rank',
    'TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `status_label`');
CALL sp_upgrade_add_column('household_statuses', 'requires_rescue',
    'TINYINT(1) NOT NULL DEFAULT 0 AFTER `severity_rank`');
CALL sp_upgrade_add_column('household_statuses', 'color_hex',
    'VARCHAR(7) DEFAULT NULL AFTER `requires_rescue`');
CALL sp_upgrade_add_column('household_statuses', 'sort_order',
    'SMALLINT UNSIGNED NOT NULL DEFAULT 999 AFTER `color_hex`');
CALL sp_upgrade_add_column('household_statuses', 'is_active',
    'TINYINT(1) NOT NULL DEFAULT 1 AFTER `sort_order`');

-- New-schema lookup constraints.
CALL sp_upgrade_assert_unique(
    'relationships',
    'relationship_key',
    'Migration stopped: duplicate relationship_key values must be resolved before adding uk_relationship_key.'
);
CALL sp_upgrade_assert_unique(
    'household_statuses',
    'status_key',
    'Migration stopped: duplicate household status_key values must be resolved before adding uk_household_status_key.'
);
CALL sp_upgrade_add_index('relationships', 'uk_relationship_key',
    'UNIQUE KEY `uk_relationship_key` (`relationship_key`)');
CALL sp_upgrade_add_index('household_statuses', 'uk_household_status_key',
    'UNIQUE KEY `uk_household_status_key` (`status_key`)');
CALL sp_upgrade_add_index('users', 'uk_users_member',
    'UNIQUE KEY `uk_users_member` (`member_id`)');

-- New-schema operational indexes and primary-key repairs.
CALL sp_upgrade_assert_key('household_disasters', 'household_disaster_id',
    'Migration stopped: household_disasters.household_disaster_id contains NULL or duplicate values.');
CALL sp_upgrade_repair_pk('household_disasters', 'household_disaster_id');
CALL sp_upgrade_assert_composite_unique('household_disasters', 'disaster_id', 'household_id',
    'Migration stopped: household_disasters has duplicate disaster_id/household_id pairs.');
CALL sp_upgrade_add_index('household_disasters', 'uk_hd_event_household',
    'UNIQUE KEY `uk_hd_event_household` (`disaster_id`, `household_id`)');
CALL sp_upgrade_add_index('household_disasters', 'idx_hd_dispatch',
    'KEY `idx_hd_dispatch` (`disaster_id`, `needs_dispatch`)');

CALL sp_upgrade_assert_key('geotagged_locations', 'location_id',
    'Migration stopped: geotagged_locations.location_id contains NULL or duplicate values.');
CALL sp_upgrade_repair_pk('geotagged_locations', 'location_id');
CALL sp_upgrade_add_index('geotagged_locations', 'idx_geo_household',
    'KEY `idx_geo_household` (`household_id`)');

CALL sp_upgrade_assert_key('hq_field_reports', 'hq_report_id',
    'Migration stopped: hq_field_reports.hq_report_id contains NULL or duplicate values.');
CALL sp_upgrade_repair_pk('hq_field_reports', 'hq_report_id');
CALL sp_upgrade_add_index('hq_field_reports', 'idx_hqfr_event',
    'KEY `idx_hqfr_event` (`disaster_id`, `household_id`)');

CALL sp_upgrade_add_index('household_status_logs', 'idx_hsl_event_household',
    'KEY `idx_hsl_event_household` (`disaster_id`, `household_id`, `created_at`)');
CALL sp_upgrade_add_index('household_members', 'idx_hm_household',
    'KEY `idx_hm_household` (`household_id`)');
CALL sp_upgrade_add_index('household_members', 'idx_hm_relationship',
    'KEY `idx_hm_relationship` (`relationship_id`)');
CALL sp_upgrade_add_index('household_members', 'idx_hm_head',
    'KEY `idx_hm_head` (`household_id`, `is_household_head`)');
CALL sp_upgrade_add_index('users', 'idx_users_household',
    'KEY `idx_users_household` (`household_id`)');

-- ---------------------------------------------------------------------
-- 2. New tables from new_schema.sql
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `member_statuses` (
  `status_id` INT NOT NULL AUTO_INCREMENT,
  `status_key` VARCHAR(50) NOT NULL,
  `status_label` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `severity_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `requires_rescue` TINYINT(1) NOT NULL DEFAULT 0,
  `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
  `color_hex` VARCHAR(7) DEFAULT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 999,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `uk_member_status_key` (`status_key`),
  KEY `idx_member_status_severity` (`severity_rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `member_disaster_statuses` (
  `member_status_id` BIGINT NOT NULL AUTO_INCREMENT,
  `disaster_id` VARCHAR(255) NOT NULL,
  `household_id` VARCHAR(255) NOT NULL,
  `member_id` VARCHAR(255) NOT NULL,
  `status_id` INT NOT NULL DEFAULT 1,
  `previous_status_id` INT DEFAULT NULL,
  `report_source` ENUM('self','household_head','proxy_member','responder','hq','sms','system') NOT NULL DEFAULT 'system',
  `reported_by_user_id` VARCHAR(255) DEFAULT NULL,
  `reported_by_member_id` VARCHAR(255) DEFAULT NULL,
  `responder_id` INT DEFAULT NULL,
  `device_token_id` BIGINT DEFAULT NULL,
  `latitude` DECIMAL(10,7) DEFAULT NULL,
  `longitude` DECIMAL(10,7) DEFAULT NULL,
  `location_label` VARCHAR(255) DEFAULT NULL,
  `location_accuracy_m` DECIMAL(8,2) DEFAULT NULL,
  `battery_level` INT DEFAULT NULL,
  `signal_strength` INT DEFAULT NULL,
  `evacuation_center_id` VARCHAR(255) DEFAULT NULL,
  `needs_rescue` TINYINT(1) NOT NULL DEFAULT 0,
  `severity_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `priority_level` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `notes` TEXT,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `verified_by_user_id` VARCHAR(255) DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `reported_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`member_status_id`),
  UNIQUE KEY `uk_mds_event_member` (`disaster_id`,`member_id`),
  KEY `idx_mds_household` (`disaster_id`,`household_id`),
  KEY `idx_mds_status` (`disaster_id`,`status_id`),
  KEY `idx_mds_rescue` (`disaster_id`,`needs_rescue`,`severity_rank`),
  KEY `idx_mds_member` (`member_id`),
  KEY `idx_mds_reported_at` (`reported_at`),
  KEY `idx_mds_status_fk` (`status_id`),
  KEY `idx_mds_prev_status_fk` (`previous_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `member_status_logs` (
  `member_status_log_id` BIGINT NOT NULL AUTO_INCREMENT,
  `disaster_id` VARCHAR(255) NOT NULL,
  `household_id` VARCHAR(255) NOT NULL,
  `member_id` VARCHAR(255) NOT NULL,
  `from_status_id` INT DEFAULT NULL,
  `to_status_id` INT NOT NULL,
  `report_source` VARCHAR(30) NOT NULL,
  `reported_by_user_id` VARCHAR(255) DEFAULT NULL,
  `reported_by_member_id` VARCHAR(255) DEFAULT NULL,
  `responder_id` INT DEFAULT NULL,
  `device_token_id` BIGINT DEFAULT NULL,
  `latitude` DECIMAL(10,7) DEFAULT NULL,
  `longitude` DECIMAL(10,7) DEFAULT NULL,
  `location_label` VARCHAR(255) DEFAULT NULL,
  `location_accuracy_m` DECIMAL(8,2) DEFAULT NULL,
  `battery_level` INT DEFAULT NULL,
  `signal_strength` INT DEFAULT NULL,
  `notes` TEXT,
  `reported_at` DATETIME DEFAULT NULL,
  `logged_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`member_status_log_id`),
  KEY `idx_msl_member_time` (`member_id`,`logged_at`),
  KEY `idx_msl_event` (`disaster_id`,`logged_at`),
  KEY `idx_msl_household` (`household_id`,`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `request_id` CHAR(36) NOT NULL,
  `user_id` VARCHAR(255) DEFAULT NULL,
  `login_value` VARCHAR(255) DEFAULT NULL,
  `verification_method` ENUM('previous_password','security_questions') NOT NULL,
  `token_hash` VARCHAR(255) DEFAULT NULL,
  `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` TIMESTAMP NOT NULL,
  `verified_at` TIMESTAMP NULL DEFAULT NULL,
  `consumed_at` TIMESTAMP NULL DEFAULT NULL,
  `requested_ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `password_reset_requests_token_hash_unique` (`token_hash`),
  KEY `password_reset_requests_user_id_consumed_at_index` (`user_id`,`consumed_at`),
  KEY `password_reset_requests_expires_at_consumed_at_index` (`expires_at`,`consumed_at`),
  KEY `password_reset_requests_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remembered_devices` (
  `remembered_device_id` CHAR(36) NOT NULL,
  `user_id` VARCHAR(255) NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `device_name` VARCHAR(150) DEFAULT NULL,
  `platform` VARCHAR(30) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`remembered_device_id`),
  UNIQUE KEY `remembered_devices_token_hash_unique` (`token_hash`),
  KEY `remembered_devices_user_id_revoked_at_index` (`user_id`,`revoked_at`),
  KEY `remembered_devices_expires_at_revoked_at_index` (`expires_at`,`revoked_at`),
  KEY `remembered_devices_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Reference data. Existing IDs are preserved; missing keys are added.
-- ---------------------------------------------------------------------

INSERT INTO `member_statuses`
  (`status_id`,`status_key`,`status_label`,`description`,`severity_rank`,`requires_rescue`,`is_terminal`,`color_hex`,`sort_order`,`is_active`)
VALUES
  (1,'unknown','Unknown / No Report','No report received for this member yet',40,0,0,'#9CA3AF',10,1),
  (2,'safe','Safe','Accounted for, no assistance needed',0,0,0,'#0D9488',20,1),
  (3,'safe_at_home','Safe at Home','Sheltering in place, no assistance needed',5,0,0,'#14B8A6',30,1),
  (4,'evacuated','Evacuated','Safe inside an evacuation center',10,0,0,'#2DD4BF',40,1),
  (5,'displaced','Displaced','Left home, not in a registered center',30,0,0,'#60A5FA',50,1),
  (6,'needs_assistance','Needs Assistance','Needs food, water, meds or transport',55,0,0,'#F59E0B',60,1),
  (7,'injured','Injured','Injured, needs medical response',75,1,0,'#F97316',70,1),
  (8,'trapped','Trapped','Trapped or stranded, needs extraction',90,1,0,'#DC2626',80,1),
  (9,'missing','Missing','Whereabouts unknown, search required',85,1,0,'#B91C1C',90,1),
  (10,'unreachable','Unreachable','Contact attempted, no response',60,0,0,'#A855F7',100,1),
  (11,'deceased','Deceased','Confirmed fatality',100,0,1,'#1F2937',110,1),
  (12,'unsafe','Unsafe','Unsafe condition requiring household follow-up',70,1,0,'#DC2626',75,1)
ON DUPLICATE KEY UPDATE
  `status_key`=VALUES(`status_key`),
  `status_label`=VALUES(`status_label`),
  `description`=VALUES(`description`),
  `severity_rank`=VALUES(`severity_rank`),
  `requires_rescue`=VALUES(`requires_rescue`),
  `is_terminal`=VALUES(`is_terminal`),
  `color_hex`=VALUES(`color_hex`),
  `sort_order`=VALUES(`sort_order`),
  `is_active`=1;

INSERT INTO `household_statuses`
  (`status_id`,`status_key`,`status_label`,`severity_rank`,`requires_rescue`,`color_hex`,`sort_order`,`is_active`)
VALUES
  (1,'active','Active',0,0,'#0D9488',10,1),
  (2,'evacuated','Evacuated',10,0,'#2A5A90',20,1),
  (3,'not_evacuated','Not Evacuated',70,1,'#962020',30,1),
  (4,'relocated','Relocated',20,0,'#60A5FA',40,1),
  (5,'displaced','Displaced',50,1,'#F59E0B',50,1),
  (6,'returned','Returned',0,0,'#3A7D57',60,1),
  (7,'unknown','Unknown / No Report',40,0,'#9CA3AF',70,1),
  (8,'safe','All Members Safe',0,0,'#0D9488',80,1),
  (9,'safe_at_home','Safe at Home',5,0,'#14B8A6',90,1),
  (12,'needs_assistance','Needs Assistance',55,1,'#F59E0B',100,1),
  (14,'trapped','Trapped - Rescue Needed',90,1,'#DC2626',110,1),
  (18,'unsafe','Unsafe',70,1,'#DC2626',120,1)
ON DUPLICATE KEY UPDATE
  `status_key`=VALUES(`status_key`),
  `status_label`=VALUES(`status_label`),
  `severity_rank`=VALUES(`severity_rank`),
  `requires_rescue`=VALUES(`requires_rescue`),
  `color_hex`=VALUES(`color_hex`),
  `sort_order`=VALUES(`sort_order`),
  `is_active`=1;

-- Keep the established six relationship IDs and add the detailed keys.
INSERT INTO `relationships`
  (`relationship_key`,`relationship_label`,`is_head`,`is_immediate_family`,`can_report_for_household`,`sort_order`)
VALUES
  ('head','Head of Household',1,1,1,10),
  ('spouse','Spouse',0,1,1,20),
  ('partner','Live-in Partner',0,1,1,25),
  ('son','Son',0,1,0,31),
  ('daughter','Daughter',0,1,0,32),
  ('stepson','Stepson',0,1,0,33),
  ('stepdaughter','Stepdaughter',0,1,0,34),
  ('adopted_child','Adopted Child',0,1,0,35),
  ('foster_child','Foster Child',0,1,0,36),
  ('father','Father',0,1,1,40),
  ('mother','Mother',0,1,1,41),
  ('stepfather','Stepfather',0,1,0,42),
  ('stepmother','Stepmother',0,1,0,43),
  ('grandfather','Grandfather',0,0,0,50),
  ('grandmother','Grandmother',0,0,0,51),
  ('grandson','Grandson',0,0,0,52),
  ('granddaughter','Granddaughter',0,0,0,53),
  ('brother','Brother',0,1,0,60),
  ('sister','Sister',0,1,0,61),
  ('brother_in_law','Brother-in-law',0,0,0,62),
  ('sister_in_law','Sister-in-law',0,0,0,63),
  ('father_in_law','Father-in-law',0,0,0,64),
  ('mother_in_law','Mother-in-law',0,0,0,65),
  ('uncle','Uncle',0,0,0,70),
  ('aunt','Aunt',0,0,0,71),
  ('nephew','Nephew',0,0,0,72),
  ('niece','Niece',0,0,0,73),
  ('cousin','Cousin',0,0,0,74),
  ('guardian','Legal Guardian',0,0,1,80),
  ('ward','Ward / Foster Child',0,0,0,81),
  ('household_help','Household Help / Kasambahay',0,0,0,90),
  ('boarder','Boarder / Bedspacer',0,0,0,91),
  ('other_relative','Other Relative',0,0,0,92),
  ('non_relative','Non-relative',0,0,0,93),
  ('unknown','Not Specified',0,0,0,999)
ON DUPLICATE KEY UPDATE
  `relationship_label`=VALUES(`relationship_label`),
  `is_head`=VALUES(`is_head`),
  `is_immediate_family`=VALUES(`is_immediate_family`),
  `can_report_for_household`=VALUES(`can_report_for_household`),
  `sort_order`=VALUES(`sort_order`),
  `is_active`=1;

-- Backfill denormalized member authority flags from relationships.
UPDATE `household_members` hm
JOIN `relationships` r ON r.`relationship_id`=hm.`relationship_id`
SET hm.`is_household_head`=r.`is_head`,
    hm.`can_report_for_household`=r.`can_report_for_household`;

-- ---------------------------------------------------------------------
-- 4. Foreign keys that are present in new_schema.sql and safe to add.
-- ---------------------------------------------------------------------

CALL sp_upgrade_assert_fk_clean('household_disasters', 'household_id', 'households', 'household_id',
    'Migration stopped: household_disasters contains an unknown household_id.');
CALL sp_upgrade_assert_fk_clean('household_disasters', 'disaster_id', 'disaster_events', 'event_id',
    'Migration stopped: household_disasters contains an unknown disaster_id.');
CALL sp_upgrade_assert_fk_clean('household_status_logs', 'household_id', 'households', 'household_id',
    'Migration stopped: household_status_logs contains an unknown household_id.');
CALL sp_upgrade_assert_fk_clean('member_disaster_statuses', 'household_id', 'households', 'household_id',
    'Migration stopped: member_disaster_statuses contains an unknown household_id.');
CALL sp_upgrade_assert_fk_clean('member_disaster_statuses', 'member_id', 'household_members', 'member_id',
    'Migration stopped: member_disaster_statuses contains an unknown member_id.');
CALL sp_upgrade_assert_fk_clean('member_disaster_statuses', 'disaster_id', 'disaster_events', 'event_id',
    'Migration stopped: member_disaster_statuses contains an unknown disaster_id.');

CALL sp_upgrade_add_fk('household_disasters','fk_hd_household',
    'FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`)');
CALL sp_upgrade_add_fk('household_disasters','fk_hd_event',
    'FOREIGN KEY (`disaster_id`) REFERENCES `disaster_events` (`event_id`)');
CALL sp_upgrade_add_fk('household_disasters','fk_hd_initial_status',
    'FOREIGN KEY (`initial_status_id`) REFERENCES `household_statuses` (`status_id`)');
CALL sp_upgrade_add_fk('household_disasters','fk_hd_status',
    'FOREIGN KEY (`current_status_id`) REFERENCES `household_statuses` (`status_id`)');
CALL sp_upgrade_add_fk('household_status_logs','fk_hsl_status',
    'FOREIGN KEY (`status_id`) REFERENCES `household_statuses` (`status_id`)');
CALL sp_upgrade_add_fk('member_disaster_statuses','fk_mds_event',
    'FOREIGN KEY (`disaster_id`) REFERENCES `disaster_events` (`event_id`)');
CALL sp_upgrade_add_fk('member_disaster_statuses','fk_mds_household',
    'FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`)');
CALL sp_upgrade_add_fk('member_disaster_statuses','fk_mds_member',
    'FOREIGN KEY (`member_id`) REFERENCES `household_members` (`member_id`)');
CALL sp_upgrade_add_fk('member_disaster_statuses','fk_mds_status',
    'FOREIGN KEY (`status_id`) REFERENCES `member_statuses` (`status_id`)');
CALL sp_upgrade_add_fk('member_disaster_statuses','fk_mds_prev_status',
    'FOREIGN KEY (`previous_status_id`) REFERENCES `member_statuses` (`status_id`)');

-- ---------------------------------------------------------------------
-- 5. New-schema views. They expose status_id, status_key, and status_label
--    from the correct lookup domain.
-- ---------------------------------------------------------------------

CREATE OR REPLACE VIEW `v_member_status_monitor` AS
SELECT
  mds.`member_status_id`,
  mds.`disaster_id`,
  de.`name` AS `event_name`,
  de.`started_at` AS `event_started_at`,
  de.`ended_at` AS `event_ended_at`,
  (de.`ended_at` IS NULL) AS `event_is_active`,
  h.`household_id`,
  h.`household_code`,
  h.`household_number`,
  h.`household_name`,
  h.`contact_number` AS `household_contact`,
  hm.`member_id`,
  TRIM(CONCAT_WS(' ',hm.`first_name`,hm.`middle_name`,hm.`last_name`)) AS `member_name`,
  TIMESTAMPDIFF(YEAR,hm.`birth_date`,CURDATE()) AS `age`,
  g.`gender_label` AS `gender`,
  r.`relationship_label`,
  hm.`is_household_head`,
  hm.`is_pwd`,
  hm.`is_senior`,
  hm.`is_pregnant`,
  u.`user_id` AS `account_user_id`,
  u.`username` AS `account_username`,
  u.`email` AS `account_email`,
  (u.`user_id` IS NOT NULL) AS `has_account`,
  mds.`status_id`,
  ms.`status_key`,
  ms.`status_label`,
  ms.`color_hex` AS `status_color`,
  mds.`severity_rank`,
  mds.`needs_rescue`,
  mds.`priority_level`,
  prev.`status_label` AS `previous_status_label`,
  mds.`report_source`,
  mds.`reported_by_user_id`,
  mds.`reported_by_member_id`,
  TRIM(CONCAT_WS(' ',rep.`first_name`,rep.`last_name`)) AS `reported_by_name`,
  mds.`latitude`,mds.`longitude`,mds.`location_label`,mds.`location_accuracy_m`,
  mds.`battery_level`,mds.`signal_strength`,mds.`evacuation_center_id`,
  mds.`notes`,mds.`is_verified`,mds.`verified_at`,mds.`reported_at`,mds.`updated_at`,
  TIMESTAMPDIFF(MINUTE,mds.`reported_at`,NOW()) AS `minutes_since_report`
FROM `member_disaster_statuses` mds
JOIN `household_members` hm ON hm.`member_id`=mds.`member_id`
JOIN `households` h ON h.`household_id`=mds.`household_id`
JOIN `member_statuses` ms ON ms.`status_id`=mds.`status_id`
JOIN `disaster_events` de ON de.`event_id`=mds.`disaster_id`
LEFT JOIN `member_statuses` prev ON prev.`status_id`=mds.`previous_status_id`
LEFT JOIN `relationships` r ON r.`relationship_id`=hm.`relationship_id`
LEFT JOIN `genders` g ON g.`gender_id`=hm.`gender_id`
LEFT JOIN `users` u ON u.`member_id`=hm.`member_id` AND u.`deleted_at` IS NULL
LEFT JOIN `household_members` rep ON rep.`member_id`=mds.`reported_by_member_id`
WHERE hm.`deleted_at` IS NULL AND h.`deleted_at` IS NULL;

CREATE OR REPLACE VIEW `v_household_status_board` AS
SELECT
  mds.`disaster_id`,h.`household_id`,h.`household_code`,h.`household_name`,
  hs.`status_id` AS `household_status_id`,
  hs.`status_label` AS `household_status_label`,
  hs.`color_hex` AS `household_status_color`,
  COUNT(*) AS `total_members`,
  SUM(ms.`status_key`='unknown') AS `unreported`,
  SUM(ms.`status_key` IN ('safe','safe_at_home','evacuated')) AS `accounted_safe`,
  SUM(ms.`requires_rescue`=1) AS `needs_rescue_count`,
  SUM(ms.`status_key`='missing') AS `missing_count`,
  SUM(ms.`status_key`='injured') AS `injured_count`,
  SUM(ms.`status_key`='deceased') AS `deceased_count`,
  MAX(mds.`severity_rank`) AS `worst_severity`,
  MAX(mds.`reported_at`) AS `last_reported_at`,
  MAX(mds.`latitude`) AS `latitude`,
  MAX(mds.`longitude`) AS `longitude`
FROM `member_disaster_statuses` mds
JOIN `household_members` hm ON hm.`member_id`=mds.`member_id` AND hm.`deleted_at` IS NULL
JOIN `households` h ON h.`household_id`=mds.`household_id`
JOIN `member_statuses` ms ON ms.`status_id`=mds.`status_id`
LEFT JOIN `household_disasters` hd ON hd.`disaster_id`=mds.`disaster_id` AND hd.`household_id`=mds.`household_id`
LEFT JOIN `household_statuses` hs ON hs.`status_id`=hd.`current_status_id`
GROUP BY mds.`disaster_id`,h.`household_id`,h.`household_code`,h.`household_name`,hs.`status_id`,hs.`status_label`,hs.`color_hex`;

-- ---------------------------------------------------------------------
-- 6. Final verification queries. Review these results after execution.
-- ---------------------------------------------------------------------

SELECT 'household_statuses' AS lookup_name, status_id, status_key, status_label
FROM household_statuses ORDER BY status_id;
SELECT 'member_statuses' AS lookup_name, status_id, status_key, status_label
FROM member_statuses ORDER BY status_id;
SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA=DATABASE()
  AND REFERENCED_TABLE_NAME IN ('household_statuses','member_statuses')
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

DROP PROCEDURE IF EXISTS sp_upgrade_add_column;
DROP PROCEDURE IF EXISTS sp_upgrade_add_index;
DROP PROCEDURE IF EXISTS sp_upgrade_add_fk;
DROP PROCEDURE IF EXISTS sp_upgrade_repair_pk;
DROP PROCEDURE IF EXISTS sp_upgrade_assert_unique;
DROP PROCEDURE IF EXISTS sp_upgrade_assert_key;
DROP PROCEDURE IF EXISTS sp_upgrade_assert_fk_clean;
DROP PROCEDURE IF EXISTS sp_upgrade_assert_composite_unique;

SET SQL_SAFE_UPDATES = 0;

-- End of migration.
