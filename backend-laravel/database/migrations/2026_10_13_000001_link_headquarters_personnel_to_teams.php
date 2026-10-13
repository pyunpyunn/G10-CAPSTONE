<?php

use App\Services\Web\HeadquartersTeamSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasTable('responders') && Schema::hasTable('rescue_teams')) {
            app(HeadquartersTeamSupport::class)->backfill();
        }
    }

    public function down(): void
    {
        // Keep operational team/member records and their history on rollback.
    }
};
