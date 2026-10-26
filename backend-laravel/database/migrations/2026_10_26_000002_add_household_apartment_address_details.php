<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('addresses')) return;
        if (! Schema::hasColumn('addresses', 'is_apartment')) Schema::table('addresses', fn (Blueprint $t) => $t->boolean('is_apartment')->nullable());
        foreach (['building_name' => 120, 'floor_number' => 30, 'unit_number' => 80, 'room_number' => 80, 'landmark' => 255] as $column => $length) {
            if (! Schema::hasColumn('addresses', $column)) Schema::table('addresses', fn (Blueprint $t) => $t->string($column, $length)->nullable());
        }
    }

    public function down(): void
    {
        // Keep recorded household address details when rolling back application code.
    }
};
