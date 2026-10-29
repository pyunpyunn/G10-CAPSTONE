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
        $service = app(ResqDatabaseIntegrity::class);
        $pdo = DB::connection()->getPdo();
        $service->preserveOrphanReferences($pdo);
        $result = $service->apply($pdo);
        if ($result['blocked']) {
            throw new RuntimeException('Unresolved Resq relationships: '.implode('; ', $result['blocked']));
        }
    }

    public function down(): void
    {
        // Keep recovery evidence; reintroducing broken references is unsafe.
    }
};
