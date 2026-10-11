<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('barangay_boundaries', function (Blueprint $table): void {
            $table->string('psgc_code', 12)->primary();
            $table->unsignedBigInteger('barangay_id')->nullable()->index();
            $table->string('barangay_code', 12)->nullable()->index();
            $table->string('barangay_name', 100);
            $table->string('city_name', 100);
            $table->longText('geometry_json');
            $table->string('source_url', 500);
            $table->string('source_note', 255);
            $table->timestamps();
        });

        $feature = json_decode(file_get_contents(database_path('boundaries/0730600049.geojson')), true, 512, JSON_THROW_ON_ERROR);
        if (($feature['properties']['psgc_10d'] ?? null) !== '0730600049'
            || ($feature['geometry']['type'] ?? null) !== 'Polygon') {
            throw new RuntimeException('The Mambaling boundary file is invalid.');
        }

        $barangayId = Schema::hasTable('barangays') && Schema::hasTable('cities')
            ? DB::table('barangays as b')->join('cities as c', 'c.city_id', '=', 'b.city_id')
                ->where('b.barangay_name', 'Mambaling')->whereIn('c.city_name', ['Cebu City', 'City of Cebu'])
                ->value('b.barangay_id')
            : null;

        DB::table('barangay_boundaries')->insert([
            'psgc_code' => '0730600049',
            'barangay_id' => $barangayId,
            'barangay_code' => '072217049',
            'barangay_name' => 'Mambaling',
            'city_name' => 'Cebu City',
            'geometry_json' => json_encode($feature['geometry'], JSON_THROW_ON_ERROR),
            'source_url' => 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4',
            'source_note' => 'PSA indicative barangay boundary, June 2016; verify locally before operational use.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_boundaries');
    }
};
