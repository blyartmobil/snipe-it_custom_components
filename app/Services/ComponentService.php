<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CheckoutableCheckedIn;
use App\Events\CheckoutableCheckedOut;
use App\Models\Asset;
use App\Models\Component;
use App\Models\ComponentSerial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComponentService
{
    /**
     * Checkout serials to an asset.
     *
     * @param  array<int>  $serialIds
     * @return Collection<ComponentSerial>
     */
    public function checkout(Component $component, Asset $asset, array $serialIds, ?string $note = null, ?User $user = null): Collection
    {
        $serials = ComponentSerial::where('component_id', $component->id)
            ->whereIn('id', $serialIds)
            ->where('status', ComponentSerial::STATUS_AVAILABLE)
            ->get();

        DB::transaction(function () use ($serials, $asset, $note, $component, $user) {
            $originalValues = $serials->map(function (ComponentSerial $s) {
                return ['id' => $s->id, 'status' => $s->getOriginal('status'), 'asset_id' => $s->getOriginal('asset_id')];
            })->all();

            foreach ($serials as $serial) {
                $serial->checkout($asset->id, $note);
            }

            event(new CheckoutableCheckedOut(
                $component,
                $asset,
                $user ?? auth()->user(),
                $note,
                $originalValues,
                $serials->count(),
            ));
        });

        return $serials;
    }

    /**
     * Checkin a single serial.
     */
    public function checkin(ComponentSerial $serial, ?string $note = null, ?User $user = null): bool
    {
        DB::transaction(function () use ($serial, $note, $user) {
            $asset = $serial->asset;
            $serial->checkin($note);

            if ($asset) {
                $component = $serial->component;
                event(new CheckoutableCheckedIn(
                    $component,
                    $asset,
                    $user ?? auth()->user(),
                    $note,
                    Carbon::now(),
                ));
            }
        });

        return true;
    }

    /**
     * Bulk checkin serials.
     *
     * @param  array<int>  $serialIds
     * @return array{checked_in: int, skipped: int}
     */
    public function bulkCheckin(Component $component, array $serialIds, ?string $note = null, ?User $user = null): array
    {
        $checkedIn = 0;
        $skipped = 0;

        DB::transaction(function () use ($component, $serialIds, $note, $user, &$checkedIn, &$skipped) {
            $serials = ComponentSerial::where('component_id', $component->id)
                ->whereIn('id', $serialIds)
                ->get();

            foreach ($serials as $serial) {
                if (! $serial->isCheckedOut()) {
                    $skipped++;
                    continue;
                }

                $asset = $serial->asset;
                $serial->checkin($note);

                if ($asset) {
                    event(new CheckoutableCheckedIn(
                        $component,
                        $asset,
                        $user ?? auth()->user(),
                        $note,
                        Carbon::now(),
                    ));
                }

                $checkedIn++;
            }
        });

        return [
            'checked_in' => $checkedIn,
            'skipped'    => $skipped,
        ];
    }

    /**
     * Generate serials from an array of serial numbers.
     *
     * @return Collection<ComponentSerial>
     */
    public function createSerials(Component $component, array $serialNumbers): Collection
    {
        $created = collect();

        DB::transaction(function () use ($component, $serialNumbers, &$created) {
            foreach ($serialNumbers as $data) {
                $serialNumber = is_array($data) ? ($data['serial'] ?? '') : $data;
                $notes = is_array($data) ? ($data['notes'] ?? null) : null;

                if (empty($serialNumber)) {
                    continue;
                }

                $serial = $component->serials()->create([
                    'serial' => $serialNumber,
                    'status' => ComponentSerial::STATUS_AVAILABLE,
                    'notes'  => $notes,
                ]);
                $created->push($serial);
            }
        });

        return $created;
    }

    /**
     * Update a serial.
     */
    public function updateSerial(ComponentSerial $serial, array $data): ComponentSerial
    {
        DB::transaction(function () use ($serial, $data) {
            $serial->update($data);
        });

        return $serial->fresh();
    }

    /**
     * Delete a serial (only if not checked out).
     */
    public function deleteSerial(ComponentSerial $serial): bool
    {
        if ($serial->isCheckedOut()) {
            return false;
        }

        DB::transaction(function () use ($serial) {
            $serial->delete();
        });

        return true;
    }
}