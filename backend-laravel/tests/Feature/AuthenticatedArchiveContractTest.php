<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticatedArchiveContractTest extends TestCase
{
    public function test_admin_receives_saved_archive_groups_with_the_existing_json_shape(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('incident_archives', function (Blueprint $table): void {
            $table->unsignedBigInteger('archive_id')->primary();
            $table->string('archive_type');
            $table->string('reference_table');
            $table->string('reference_id');
            $table->text('archive_note');
            $table->timestamp('archived_at');
        });

        DB::table('incident_archives')->insert([
            'archive_id' => 7,
            'archive_type' => 'saved_log_group',
            'reference_table' => 'resource-requests',
            'reference_id' => 'AG-7',
            'archive_note' => json_encode(['category' => 'resource-requests', 'records' => [['id' => 'RR-7']]]),
            'archived_at' => '2026-01-01 12:00:00',
        ]);

        Sanctum::actingAs(User::query()->create(['user_id' => 'ADMIN-TEST', 'role' => 'admin']));

        $this->getJson('/api/v1/archive/saved-groups')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.groups.0.id', 'AG-7')
            ->assertJsonPath('data.groups.0.category', 'resource-requests')
            ->assertJsonPath('data.groups.0.records.0.id', 'RR-7');
    }
}
