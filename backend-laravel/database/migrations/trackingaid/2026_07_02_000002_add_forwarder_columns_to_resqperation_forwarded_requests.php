<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('services.trackingaid.connection', 'trackingaid');
    }

    private function tableName(): string
    {
        return (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->connectionName());
        $tableName = $this->tableName();

        if (! $schema->hasTable($tableName) || $schema->hasColumn($tableName, 'forwarded_by_user_id')) {
            return;
        }

        $schema->table($tableName, function (Blueprint $table): void {
            $table->string('forwarded_by_user_id', 120)->nullable()->after('validated_by_user_id');
            $table->string('forwarded_by_name', 255)->nullable()->after('forwarded_by_user_id');
            $table->string('forwarded_by_role', 80)->nullable()->after('forwarded_by_name');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connectionName());
        $tableName = $this->tableName();

        if (! $schema->hasTable($tableName) || ! $schema->hasColumn($tableName, 'forwarded_by_user_id')) {
            return;
        }

        $schema->table($tableName, function (Blueprint $table): void {
            $table->dropColumn([
                'forwarded_by_user_id',
                'forwarded_by_name',
                'forwarded_by_role',
            ]);
        });
    }
};
