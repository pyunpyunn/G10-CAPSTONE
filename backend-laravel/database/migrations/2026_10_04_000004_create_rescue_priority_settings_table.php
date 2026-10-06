<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rescue_priority_settings', function (Blueprint $table): void {
            $table->bigIncrements('version');
            $table->unsignedTinyInteger('impact_weight');
            $table->unsignedTinyInteger('vulnerability_weight');
            $table->unsignedTinyInteger('unreported_weight');
            $table->unsignedTinyInteger('no_contact_weight');
            $table->string('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        DB::table('rescue_priority_settings')->insert([
            'impact_weight' => 30,
            'vulnerability_weight' => 30,
            'unreported_weight' => 25,
            'no_contact_weight' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rescue_priority_settings');
    }
};
