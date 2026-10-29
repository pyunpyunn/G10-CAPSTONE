<?php

use App\Services\Shared\LegacyResidentCredentialCleanup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $result = app(LegacyResidentCredentialCleanup::class)->run(DB::connection());
        if ($result['unresolved']) {
            throw new RuntimeException('Some resident accounts lack recognized password hashes; passwords were preserved for review.');
        }
    }

    public function down(): void
    {
        // Plaintext credentials must never be restored.
    }
};
