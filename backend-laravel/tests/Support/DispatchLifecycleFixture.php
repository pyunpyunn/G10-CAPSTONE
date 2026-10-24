<?php
namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait DispatchLifecycleFixture
{
    private function lifecycleFixture(): void
    {
        $this->app->instance(\App\Services\Shared\BarangayProfileService::class, new class extends \App\Services\Shared\BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1, 'name' => 'Test Mambaling']; }
        });
        foreach ([
            'households' => ['address_id'=>'integer','household_code'=>'string','contact_number'=>'string','member_count'=>'integer'],
            'household_members' => ['is_pwd'=>'boolean','is_senior'=>'boolean','is_pregnant'=>'boolean'],
            'household_disasters' => ['priority_level'=>'string','last_battery_level'=>'integer','last_reported_at'=>'timestamp'],
            'responders' => ['full_name'=>'string','contact_number'=>'string','deleted_at'=>'timestamp'],
            'responder_assignments' => ['assignment_code'=>'string','responder_count'=>'integer','dispatch_type'=>'string','assigned_area'=>'string','priority_level'=>'string','dispatch_notes'=>'text','created_by_admin_id'=>'string','assigned_at'=>'timestamp'],
            'disaster_events' => ['type_id'=>'integer','severity_level_id'=>'integer'],
        ] as $table => $columns) Schema::table($table, function (Blueprint $t) use ($columns) {
            foreach ($columns as $name=>$type) $t->$type($name)->nullable();
        });
        Schema::create('sitios', function (Blueprint $t) { $t->integer('sitio_id')->primary(); $t->integer('barangay_id'); $t->string('sitio_name'); });
        Schema::create('puroks', function (Blueprint $t) { $t->integer('purok_id')->primary(); $t->integer('sitio_id'); $t->string('purok_name'); });
        Schema::create('addresses', function (Blueprint $t) { $t->integer('address_id')->primary(); $t->integer('sitio_id'); $t->integer('purok_id'); foreach (['purok_sitio','full_address','house_number'] as $c) $t->string($c)->nullable(); });
        Schema::create('device_tokens', function (Blueprint $t) { $t->integer('device_id')->primary(); $t->string('household_id'); $t->boolean('is_active'); });
        Schema::create('member_vulnerable_groups', function (Blueprint $t) { $t->string('member_id'); });
        Schema::create('geotagged_locations', function (Blueprint $t) { $t->integer('location_id')->primary(); $t->string('household_id'); $t->double('latitude'); $t->double('longitude'); $t->string('location_label')->nullable(); $t->timestamps(); });
        Schema::create('rescue_priority_settings', function (Blueprint $t) { $t->increments('version'); foreach (['impact_weight','vulnerability_weight','unreported_weight','no_contact_weight'] as $c) $t->integer($c); });
        Schema::create('rescue_teams', function (Blueprint $t) { $t->integer('team_id')->primary(); foreach (['team_name','team_code','team_type','duty_status'] as $c) $t->string($c); $t->integer('leader_responder_id')->nullable(); $t->timestamps(); });
        Schema::create('disaster_types', function (Blueprint $t) { $t->integer('type_id')->primary(); $t->string('type_name'); });
        Schema::create('severity_levels', function (Blueprint $t) { $t->integer('severity_id')->primary(); $t->string('severity_key'); $t->string('severity_label'); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('audit_log_id'); foreach (['user_id','role_key','module','action','reference_table','reference_id','old_values','new_values','ip_address','user_agent'] as $c) $t->text($c)->nullable(); $t->timestamp('created_at')->nullable(); });
        Schema::create('responder_routes', function (Blueprint $t) { $t->integer('route_id')->primary(); $t->integer('assignment_id'); $t->integer('responder_id'); foreach (['household_id','route_name','route_status','route_polyline','navigation_steps'] as $c) $t->text($c)->nullable(); foreach (['start_latitude','start_longitude','end_latitude','end_longitude','estimated_distance_km','estimated_duration_min'] as $c) $t->double($c)->nullable(); $t->timestamps(); });
        Schema::create('route_coordinates', function (Blueprint $t) { $t->integer('coordinate_id')->primary(); $t->integer('route_id'); $t->double('latitude'); $t->double('longitude'); $t->integer('sequence_order'); $t->double('accuracy_m')->nullable(); $t->timestamp('recorded_at')->nullable(); });
        Schema::create('responder_location_logs', function (Blueprint $t) { $t->integer('log_id')->primary(); $t->integer('responder_id'); $t->double('latitude'); $t->double('longitude'); $t->timestamp('logged_at'); $t->timestamps(); });
        DB::table('rescue_priority_settings')->insert(['impact_weight'=>30,'vulnerability_weight'=>30,'unreported_weight'=>25,'no_contact_weight'=>15]);
        DB::table('sitios')->insert([['sitio_id'=>1,'barangay_id'=>1,'sitio_name'=>'Test Priority'],['sitio_id'=>2,'barangay_id'=>1,'sitio_name'=>'Test Standby']]);
        foreach ([1,2] as $id) { DB::table('puroks')->insert(['purok_id'=>$id,'sitio_id'=>$id,'purok_name'=>'Purok '.$id]); DB::table('addresses')->insert(['address_id'=>$id,'purok_id'=>$id,'sitio_id'=>$id,'purok_sitio'=>'Purok '.$id.', Test '.($id===1?'Priority':'Standby')]); }
        DB::table('households')->where('household_id','HH-4')->update(['address_id'=>1,'member_count'=>2]);
        foreach (['HH-5','HH-6'] as $id) DB::table('households')->insert(['household_id'=>$id,'household_name'=>'Test '.$id,'address_id'=>1,'member_count'=>1]);
        DB::table('households')->insert(['household_id'=>'HH-7','household_name'=>'Test safe comparator','address_id'=>2,'member_count'=>0]);
        DB::table('household_members')->insert([['member_id'=>'M-3','household_id'=>'HH-5','is_pwd'=>true],['member_id'=>'M-4','household_id'=>'HH-6','is_pwd'=>true]]);
        DB::table('household_members')->where('household_id','HH-4')->update(['is_pwd'=>true]);
        DB::table('rescue_teams')->insert(['team_id'=>1,'team_name'=>'Test Rescue','team_code'=>'TST','team_type'=>'Rescue','duty_status'=>'available']);
        DB::table('responders')->update(['team_id'=>1,'full_name'=>'Test responder','duty_status'=>'available']);
        DB::table('responder_assignments')->delete();
        foreach (['HH-4','HH-5','HH-6'] as $i=>$id) DB::table('geotagged_locations')->insert(['location_id'=>$i+1,'household_id'=>$id,'latitude'=>10.29+$i*.001,'longitude'=>123.88+$i*.001,'updated_at'=>now(),'created_at'=>now()]);
    }
}
