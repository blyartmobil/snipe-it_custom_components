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
        Schema::whenTableDoesntHaveColumn('settings', 'guest_acceptance_enabled', function () {
            Schema::table('settings', function (Blueprint $table) {
                $table->boolean('guest_acceptance_enabled')->default(false);
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::whenTableHasColumn('settings', 'guest_acceptance_enabled', function () {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('guest_acceptance_enabled');
            });
        });
    }
};