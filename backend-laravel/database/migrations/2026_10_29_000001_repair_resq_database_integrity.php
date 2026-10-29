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
            logger()->warning('Resq database relationships still require review.', ['blocked' => $result['blocked']]);
        }
    }

    public function down(): void
    {
        // Integrity repairs must not silently remove protection from operational records.
    }
};
