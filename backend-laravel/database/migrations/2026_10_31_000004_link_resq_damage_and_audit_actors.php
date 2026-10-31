<?php

use App\Services\Shared\ResqDatabaseIntegrity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        $result = app(ResqDatabaseIntegrity::class)->apply(DB::connection()->getPdo());
        if ($result['blocked']) throw new RuntimeException('Damage/audit references require review; original records retained: '.implode('; ', $result['blocked']));
    }

    public function down(): void
    {
        // Retain catalog references and auditable actor identities; never cascade away history.
    }
};
