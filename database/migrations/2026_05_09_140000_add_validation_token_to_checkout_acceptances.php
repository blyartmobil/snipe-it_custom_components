<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::whenTableDoesntHaveColumn('checkout_acceptances', 'validation_token', function () {
            Schema::table('checkout_acceptances', function (Blueprint $table) {
                $table->uuid('validation_token')->nullable()->unique()->after('id');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::whenTableHasColumn('checkout_acceptances', 'validation_token', function () {
            Schema::table('checkout_acceptances', function (Blueprint $table) {
                $table->dropUnique(['validation_token']);
                $table->dropColumn('validation_token');
            });
        });
    }
};