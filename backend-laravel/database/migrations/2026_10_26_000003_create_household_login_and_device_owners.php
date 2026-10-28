<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Pending installations use existing tables directly; never create the retired tables.
        (require __DIR__.'/2026_10_28_000004_consolidate_household_access.php')->up();
    }

    public function down(): void
    {
        // Preserve household login links and saved phone owners.
    }
};
