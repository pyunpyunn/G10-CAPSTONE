<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trusted_households')) {
            return;
        }

        Schema::table('trusted_households', function (Blueprint $table): void {
            if (! Schema::hasColumn('trusted_households', 'relationship_id')) {
                $table->string('relationship_id', 100)->nullable()->after('reason');
            }

            if (! Schema::hasColumn('trusted_households', 'relationship_label')) {
                $table->string('relationship_label', 100)->nullable()->after('relationship_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trusted_households')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('trusted_households', 'relationship_label') ? 'relationship_label' : null,
            Schema::hasColumn('trusted_households', 'relationship_id') ? 'relationship_id' : null,
        ]));

        if ($columns) {
            Schema::table('trusted_households', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
