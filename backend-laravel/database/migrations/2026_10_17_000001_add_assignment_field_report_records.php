<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('responder_field_reports')) {
            Schema::create('responder_field_reports', function (Blueprint $table): void {
                $table->string('report_id')->primary();
                $table->unsignedBigInteger('responder_id');
                $table->string('disaster_id');
                $table->string('household_id');
                $table->unsignedBigInteger('assignment_id')->nullable()->unique();
                $table->string('submitted_by_user_id')->nullable();
                $table->unsignedBigInteger('status_log_id')->nullable();
                $table->string('incident_type', 80)->nullable();
                $table->string('incident_type_other', 255)->nullable();
                $table->string('property_damage', 80)->nullable();
                $table->unsignedInteger('death_count')->default(0);
                $table->unsignedInteger('injured_count')->default(0);
                $table->string('idempotency_key', 100)->nullable();
                $table->string('payload_hash', 64)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        } else {
            Schema::table('responder_field_reports', function (Blueprint $table): void {
                if (! Schema::hasColumn('responder_field_reports', 'assignment_id')) {
                    $table->unsignedBigInteger('assignment_id')->nullable()->unique();
                }
                if (! Schema::hasColumn('responder_field_reports', 'submitted_by_user_id')) {
                    $table->string('submitted_by_user_id')->nullable();
                }
                if (! Schema::hasColumn('responder_field_reports', 'status_log_id')) {
                    $table->unsignedBigInteger('status_log_id')->nullable();
                }
                if (! Schema::hasColumn('responder_field_reports', 'incident_type')) {
                    $table->string('incident_type', 80)->nullable();
                }
                if (! Schema::hasColumn('responder_field_reports', 'incident_type_other')) {
                    $table->string('incident_type_other', 255)->nullable();
                }
                if (! Schema::hasColumn('responder_field_reports', 'property_damage')) {
                    $table->string('property_damage', 80)->nullable();
                }
                if (! Schema::hasColumn('responder_field_reports', 'death_count')) {
                    $table->unsignedInteger('death_count')->default(0);
                }
                if (! Schema::hasColumn('responder_field_reports', 'injured_count')) {
                    $table->unsignedInteger('injured_count')->default(0);
                }
                if (! Schema::hasColumn('responder_field_reports', 'idempotency_key')) {
                    $table->string('idempotency_key', 100)->nullable();
                }
                if (! Schema::hasColumn('responder_field_reports', 'payload_hash')) {
                    $table->string('payload_hash', 64)->nullable();
                }
                if (! Schema::hasColumn('responder_field_reports', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('responder_field_report_members')) {
            Schema::create('responder_field_report_members', function (Blueprint $table): void {
                $table->bigIncrements('detail_id');
                $table->string('report_id');
                $table->string('household_id');
                $table->string('disaster_id');
                $table->string('member_id');
                $table->unsignedBigInteger('status_id');
                $table->string('status_key', 50);
                $table->timestamp('assessed_at');
                $table->timestamps();
                $table->unique(['report_id', 'member_id'], 'rf_report_member_unique');
                $table->index(['disaster_id', 'household_id'], 'rf_member_event_household_idx');
            });
        }
    }

    public function down(): void
    {
        // Preserve submitted field reports and their member assessments on rollback.
    }
};
