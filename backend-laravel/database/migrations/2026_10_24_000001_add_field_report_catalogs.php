<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('property_damage_levels')) Schema::create('property_damage_levels', function (Blueprint $t) {
            $t->string('damage_key',80)->primary(); $t->string('label'); $t->unsignedInteger('sort_order'); $t->boolean('is_active')->default(true);
        });
        foreach (['no_damage'=>'None','minor_damage'=>'Minor','major_damage'=>'Major','completely_destroyed'=>'Destroyed','not_yet_assessed'=>'Not assessed'] as $key=>$label) {
            DB::table('property_damage_levels')->insertOrIgnore(['damage_key'=>$key,'label'=>$label,'sort_order'=>array_search($key,['no_damage','minor_damage','major_damage','completely_destroyed','not_yet_assessed']),'is_active'=>true]);
        }
        if (Schema::hasTable('member_statuses')) {
            foreach (['counts_as_death','counts_as_injury'] as $column) if (!Schema::hasColumn('member_statuses',$column)) Schema::table('member_statuses',fn (Blueprint $t)=>$t->boolean($column)->default(false));
            DB::table('member_statuses')->whereIn('status_key',['deceased','dead'])->update(['counts_as_death'=>true]);
            DB::table('member_statuses')->where('status_key','injured')->update(['counts_as_injury'=>true]);
        }
    }
    public function down(): void { /* Catalog data is preserved on rollback. */ }
};
