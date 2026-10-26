<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('mapping_hazard_layers', function (Blueprint $table) {
        $table->id(); $table->string('barangay_id')->index(); $table->string('name');
        $table->string('source'); $table->string('source_url')->nullable(); $table->date('assessed_at');
        $table->json('geojson'); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('mapping_hazard_layers'); }
};
