<?php

use App\Models\Component;
use App\Models\ComponentSerial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up()
    {
        try {
            DB::beginTransaction();

            // 1. Fix Ghost Inventory: Components with qty=1 but no serials or all serials deleted
            //    Reset qty to 0 for components that have ghost inventory counts
            $components = Component::withTrashed()->get();

            foreach ($components as $component) {
                $serialsCount = $component->serials()
                    ->whereNotIn('status', [ComponentSerial::STATUS_RETIRED])
                    ->count();

                // If qty says 1 but no non-retired serials exist, reset qty to 0
                if ((int) $component->qty >= 1 && $serialsCount === 0) {
                    // Delete any TEMP serial records that may exist for qty=0 items
                    $component->serials()
                        ->where('serial', 'like', 'TEMP-' . $component->id . '-%')
                        ->delete();

                    // Reset qty to 0
                    $component->qty = 0;
                    $component->saveQuietly();

                    Log::info("Corrective migration: Reset qty to 0 for component {$component->id} ({$component->name}) - had ghost inventory");
                }

                // Delete orphaned TEMP serials where qty=0 (shouldn't exist but belt-and-suspenders)
                $componentSerialsCount = $component->serials()->count();
                if ((int) $component->qty === 0 && $componentSerialsCount === 0) {
                    // Already correct, nothing to do
                    continue;
                }
            }

            // 2. Fix Missing Assignments: For components_assets rows where assigned_qty > 1,
            //    ensure enough serials are checked out
            $pivotRows = DB::table('components_assets')->get();

            foreach ($pivotRows as $pivot) {
                $assignedQty = (int) ($pivot->assigned_qty ?? 1);
                $currentlyCheckedOut = DB::table('component_serials')
                    ->where('component_id', $pivot->component_id)
                    ->where('asset_id', $pivot->asset_id)
                    ->where('status', 'checked_out')
                    ->count();

                $missing = $assignedQty - $currentlyCheckedOut;

                if ($missing > 0) {
                    // Find available serials to assign
                    $available = DB::table('component_serials')
                        ->where('component_id', $pivot->component_id)
                        ->where('status', 'available')
                        ->orderBy('id')
                        ->take($missing)
                        ->get();

                    foreach ($available as $serial) {
                        DB::table('component_serials')
                            ->where('id', $serial->id)
                            ->update([
                                'status'      => 'checked_out',
                                'asset_id'    => $pivot->asset_id,
                                'checkout_at' => $pivot->created_at ?? now(),
                                'updated_at'  => now(),
                            ]);
                    }

                    if ($available->count() < $missing) {
                        Log::warning("Corrective migration: Could only find {$available->count()} available serials for component {$pivot->component_id} when {$missing} were needed for asset {$pivot->asset_id}");
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Corrective component serials migration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function down()
    {
        // No reverse operation needed for this corrective migration
        Log::info('Corrective component serials migration rolled back - no data changes applied');
    }
};