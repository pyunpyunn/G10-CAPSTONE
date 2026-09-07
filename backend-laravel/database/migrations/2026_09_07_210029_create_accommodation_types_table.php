<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_key')->unique();
            $table->string('type_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_types');
    }
};