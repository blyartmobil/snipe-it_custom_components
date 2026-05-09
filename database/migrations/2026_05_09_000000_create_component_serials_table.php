<?php

use App\Models\Component;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Create the component_serials table
        Schema::create('component_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('component_id');
            $table->string('serial');
            $table->enum('status', ['available', 'checked_out', 'defective', 'retired'])->default('available');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('checkout_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('component_id')
                  ->references('id')->on('components')
                  ->onDelete('cascade');
            $table->foreign('asset_id')
                  ->references('id')->on('assets')
                  ->onDelete('set null');

            $table->index(['component_id', 'status']);
            $table->unique(['component_id', 'serial']); // Bug fix: compound unique for serial within component scope
        });

        // 2. Migrate existing data
        try {
            DB::beginTransaction();

            $components = Component::withTrashed()->get();
            foreach ($components as $component) {
                $qty = (int) $component->qty;
                if ($qty < 1) {
                    continue;
                }

                // Create serial records for each unit of quantity
                for ($i = 1; $i <= $qty; $i++) {
                    DB::table('component_serials')->insert([
                        'component_id' => $component->id,
                        'serial'       => 'TEMP-' . $component->id . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                        'status'       => 'available',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

            // 3. Migrate existing checkouts from components_assets pivot
            $checkouts = DB::table('components_assets')->get();
            foreach ($checkouts as $checkout) {
                $assignedQty = (int) ($checkout->assigned_qty ?? 1);
                for ($i = 0; $i < $assignedQty; $i++) {
                    $available = DB::table('component_serials')
                        ->where('component_id', $checkout->component_id)
                        ->where('status', 'available')
                        ->orderBy('id')
                        ->first();

                    if ($available) {
                        DB::table('component_serials')
                            ->where('id', $available->id)
                            ->update([
                                'status'      => 'checked_out',
                                'asset_id'    => $checkout->asset_id,
                                'checkout_at' => $checkout->created_at ?? now(),
                                'updated_at'  => now(),
                            ]);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Component serials migration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function down()
    {
        Schema::dropIfExists('component_serials');
    }
};