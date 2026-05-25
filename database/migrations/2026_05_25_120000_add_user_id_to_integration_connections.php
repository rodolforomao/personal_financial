<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('integration_connections', 'user_id')) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('workspace_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (! $this->indexExists('integration_connections', 'integration_connections_workspace_provider_index')) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->index(['workspace_id', 'provider'], 'integration_connections_workspace_provider_index');
            });
        }

        if ($this->indexExists('integration_connections', 'integration_connections_workspace_id_provider_unique')) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->dropUnique('integration_connections_workspace_id_provider_unique');
            });
        }

        if (! $this->indexExists('integration_connections', 'integration_connections_workspace_provider_user_unique')) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->unique(['workspace_id', 'provider', 'user_id'], 'integration_connections_workspace_provider_user_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('integration_connections', 'integration_connections_workspace_provider_index')) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->dropIndex('integration_connections_workspace_provider_index');
            });
        }

        if ($this->indexExists('integration_connections', 'integration_connections_workspace_provider_user_unique')) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->dropUnique('integration_connections_workspace_provider_user_unique');
            });
        }

        if (Schema::hasColumn('integration_connections', 'user_id')) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (! $this->indexExists('integration_connections', 'integration_connections_workspace_id_provider_unique')) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->unique(['workspace_id', 'provider']);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
