<?php

use App\Services\Shared\SharedResqSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SharedResqSchema::class)->run();
    }

    public function down(): void
    {
        throw new RuntimeException('Shared database additions must be preserved for other applications.');
    }
};
