<?php

use App\Services\Shared\ResqDatabaseIntegrity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $result = app(ResqDatabaseIntegrity::class)->apply(DB::connection()->getPdo());
        if ($result['blocked']) {
            throw new RuntimeException('Resq module relationship audit failed: '.implode('; ', $result['blocked']));
        }
    }

    public function down(): void
    {
        // Do not remove operational integrity checks on rollback.
    }
};
