<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_types', function (Blueprint $table) {
            $table->increments('type_id');
            $table->string('type_key', 50)->nullable()->unique();
            $table->string('type_label', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_types');
    }
};
