<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('landing_inquiries')) {
            return;
        }

        Schema::create('landing_inquiries', function (Blueprint $table): void {
            $table->id('inquiry_id');
            $table->string('name', 150);
            $table->string('organization', 150)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('message');
            $table->string('status', 30)->default('new');
            $table->string('source_page', 80)->default('landing_page');
            $table->string('ip_address', 80)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('handled_by_user_id', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_inquiries');
    }
};
