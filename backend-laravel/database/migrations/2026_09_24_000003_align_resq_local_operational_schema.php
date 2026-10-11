<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The imported resq_local dump predates the operational responder fields
        // used by the HQ workspaces. Add only fields that are absent so existing
        // installations and their data are left intact.
        $responderColumns = [
            'created_by_admin_id' => 'varchar(255) NULL',
            'team_id' => 'int NULL',
            'emergency_contact_name' => 'varchar(255) NULL',
            'emergency_contact_number' => 'varchar(255) NULL',
            'date_of_birth' => 'date NULL',
            'gender' => 'varchar(50) NULL',
            'blood_type' => 'varchar(10) NULL',
            'address' => 'varchar(255) NULL',
            'skills' => 'text NULL',
            'training_notes' => 'text NULL',
            'certification_reference' => 'varchar(255) NULL',
            'equipment_notes' => 'text NULL',
            'is_deployed' => 'tinyint(1) NOT NULL DEFAULT 0',
            'duty_status' => "varchar(30) NOT NULL DEFAULT 'standby'",
            'last_active_at' => 'datetime NULL',
            'deleted_at' => 'datetime NULL',
        ];

        foreach ($responderColumns as $column => $definition) {
            if (! Schema::hasColumn('responders', $column)) {
                DB::statement("ALTER TABLE responders ADD COLUMN {$column} {$definition}");
            }
        }

        if (! Schema::hasColumn('users', 'must_change_password')) {
            DB::statement('ALTER TABLE users ADD COLUMN must_change_password tinyint(1) NOT NULL DEFAULT 0');
        }

        // IDs from the original authentication tables used unicode_ci while the
        // imported disaster tables use MySQL 8's 0900 collation. A join between
        // them otherwise raises error 1267 and prevents household loading.
        DB::statement('ALTER TABLE users MODIFY user_id varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL');
        DB::statement('ALTER TABLE responders MODIFY user_id varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL');
    }

    public function down(): void
    {
        // Do not drop compatibility columns: they can contain operational data.
    }
};
