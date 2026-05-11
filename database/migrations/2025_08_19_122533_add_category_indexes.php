<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table.
     * Uses a raw information_schema query to avoid dependency on doctrine/dbal,
     * which is no longer shipped with Laravel by default.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $results = DB::select(
            'SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [DB::connection()->getDatabaseName(), $table, $indexName]
        );
        return $results[0]->count > 0;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!$this->hasIndex('categories', 'categories_deleted_at_index')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->index(['deleted_at']);
            });
        }

        if (!$this->hasIndex('accessories', 'accessories_deleted_at_category_id_index')) {
            Schema::table('accessories', function (Blueprint $table) {
                $table->index(['deleted_at', 'category_id']);
            });
        }

        if (!$this->hasIndex('consumables', 'consumables_deleted_at_category_id_index')) {
            Schema::table('consumables', function (Blueprint $table) {
                $table->index(['deleted_at', 'category_id']);
            });
        }

        if (!$this->hasIndex('components', 'components_deleted_at_category_id_index')) {
            Schema::table('components', function (Blueprint $table) {
                $table->index(['deleted_at', 'category_id']);
            });
        }

        if (!$this->hasIndex('licenses', 'licenses_deleted_at_category_id_index')) {
            Schema::table('licenses', function (Blueprint $table) {
                $table->index(['deleted_at', 'category_id']);
            });
        }

        if (!$this->hasIndex('models', 'models_deleted_at_category_id_index')) {
            Schema::table('models', function (Blueprint $table) {
                $table->index(['deleted_at', 'category_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasIndex('categories', 'categories_deleted_at_index')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropIndex(['deleted_at']);
            });
        }

        if ($this->hasIndex('accessories', 'accessories_deleted_at_category_id_index')) {
            Schema::table('accessories', function (Blueprint $table) {
                $table->dropIndex(['deleted_at', 'category_id']);
            });
        }

        if ($this->hasIndex('consumables', 'consumables_deleted_at_category_id_index')) {
            Schema::table('consumables', function (Blueprint $table) {
                $table->dropIndex(['deleted_at', 'category_id']);
            });
        }

        if ($this->hasIndex('components', 'components_deleted_at_category_id_index')) {
            Schema::table('components', function (Blueprint $table) {
                $table->dropIndex(['deleted_at', 'category_id']);
            });
        }

        if ($this->hasIndex('licenses', 'licenses_deleted_at_category_id_index')) {
            Schema::table('licenses', function (Blueprint $table) {
                $table->dropIndex(['deleted_at', 'category_id']);
            });
        }

        if ($this->hasIndex('models', 'models_deleted_at_category_id_index')) {
            Schema::table('models', function (Blueprint $table) {
                $table->dropIndex(['deleted_at', 'category_id']);
            });
        }
    }
};