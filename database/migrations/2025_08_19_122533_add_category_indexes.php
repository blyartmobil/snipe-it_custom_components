<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $sm->listTableIndexes($table);
        return array_key_exists($indexName, $indexes);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check and add indexes only if they don't already exist
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
        // Check and drop indexes only if they exist
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
