<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }

            if (! Schema::hasColumn('users', 'security_question_1')) {
                $table->string('security_question_1', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'security_answer_1')) {
                $table->string('security_answer_1')->nullable();
            }

            if (! Schema::hasColumn('users', 'security_question_2')) {
                $table->string('security_question_2', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'security_answer_2')) {
                $table->string('security_answer_2')->nullable();
            }

            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'security_question_1',
                'security_answer_1',
                'security_question_2',
                'security_answer_2',
                'password_changed_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
