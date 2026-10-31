<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ResponderFieldReport;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResqIsolatedObjectLinksTest extends TestCase
{
    public function test_report_resolves_the_persisted_damage_label_and_prevents_dangling_catalog_keys(): void
    {
        Schema::create('property_damage_levels', function (Blueprint $t): void { $t->string('damage_key')->primary(); $t->string('label'); });
        Schema::create('responder_field_reports', function (Blueprint $t): void {
            $t->integer('report_id')->primary();
            $t->string('property_damage')->nullable();
            $t->foreign('property_damage')->references('damage_key')->on('property_damage_levels')->restrictOnDelete();
        });
        DB::table('property_damage_levels')->insert(['damage_key' => 'minor_damage', 'label' => 'Shared catalog label']);
        DB::table('responder_field_reports')->insert(['report_id' => 1, 'property_damage' => 'minor_damage']);
        $this->assertSame('Shared catalog label', ResponderFieldReport::with('damageLevel')->findOrFail(1)->damageLevel->label);
        $this->expectException(QueryException::class);
        DB::table('property_damage_levels')->where('damage_key', 'minor_damage')->delete();
    }

    public function test_audit_actor_can_be_anonymous_but_a_recorded_actor_must_exist(): void
    {
        Schema::create('users', fn (Blueprint $t) => $t->string('user_id')->primary());
        Schema::create('audit_logs', function (Blueprint $t): void {
            $t->integer('audit_log_id')->primary();
            $t->string('user_id')->nullable();
            $t->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
        });
        DB::table('users')->insert(['user_id' => 'ACTOR-1']);
        DB::table('audit_logs')->insert([['audit_log_id' => 1, 'user_id' => 'ACTOR-1'], ['audit_log_id' => 2, 'user_id' => null]]);
        $this->assertSame('ACTOR-1', AuditLog::with('user')->findOrFail(1)->user->getKey());
        $this->assertNull(AuditLog::with('user')->findOrFail(2)->user);
        $this->expectException(QueryException::class);
        DB::table('audit_logs')->insert(['audit_log_id' => 3, 'user_id' => 'MISSING-ACTOR']);
    }
}
