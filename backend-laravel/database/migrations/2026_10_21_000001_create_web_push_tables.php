<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('web_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 255)->index();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('endpoint_hash', 64)->unique();
            $table->text('subscription');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('web_push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id')->index();
            $table->string('notice_hash', 64);
            $table->timestamp('created_at');
            $table->unique(['subscription_id', 'notice_hash']);
        });
        if (Schema::hasTable('notifications')) {
            foreach (['advisory_source' => 'string', 'advisory_fingerprint' => 'string', 'advisory_title' => 'string', 'advisory_source_url' => 'text'] as $column => $type) {
                if (! Schema::hasColumn('notifications', $column)) {
                    Schema::table('notifications', function (Blueprint $table) use ($column, $type): void {
                        $field = $table->$type($column)->nullable();
                        if ($column === 'advisory_fingerprint') $field->unique();
                    });
                }
            }
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('web_push_deliveries');
        Schema::dropIfExists('web_push_subscriptions');
    }
};
