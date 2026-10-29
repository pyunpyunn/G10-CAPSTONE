<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('purok_boundaries')) {
            Schema::create('purok_boundaries', function (Blueprint $table): void {
                $table->unsignedBigInteger('purok_id')->primary();
                $table->longText('geometry_json');
                $table->text('source');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('household_geographic_classifications')) {
            Schema::create('household_geographic_classifications', function (Blueprint $table): void {
                $table->string('household_id')->primary();
                $table->string('status', 50)->index();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedBigInteger('sitio_id')->nullable();
                $table->unsignedBigInteger('purok_id')->nullable();
                $table->timestamps();
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('household_geographic_classifications');
        Schema::dropIfExists('purok_boundaries');
    }
};
