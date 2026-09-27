<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'login_id' => 'varchar(255) NULL',
            'assigned_center_id' => 'varchar(255) NULL',
            'household_id' => 'varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL',
            'member_id' => 'bigint NULL',
            'temp_password' => 'varchar(255) NULL',
            'profile_photo' => 'varchar(255) NULL',
            'security_question_1' => 'varchar(255) NULL',
            'security_answer_1' => 'varchar(255) NULL',
            'security_question_2' => 'varchar(255) NULL',
            'security_answer_2' => 'varchar(255) NULL',
            'password_changed_at' => 'datetime NULL',
            'deleted_at' => 'datetime NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                DB::statement("ALTER TABLE users ADD COLUMN {$column} {$definition}");
            }
        }
    }

    public function down(): void
    {
        // Compatibility columns are intentionally retained to preserve data.
    }
};
