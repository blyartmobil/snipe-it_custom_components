<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ComponentSerial;

class ComponentSerialObserver
{
    /**
     * Handle the ComponentSerial "created" event.
     *
     * @return void
     */
    public function created(ComponentSerial $componentSerial)
    {
        $componentSerial->component?->syncQtyFromSerials();
    }

    /**
     * Handle the ComponentSerial "updated" event.
     *
     * @return void
     */
    public function updated(ComponentSerial $componentSerial)
    {
        $componentSerial->component?->syncQtyFromSerials();
    }

    /**
     * Handle the ComponentSerial "deleted" event.
     *
     * @return void
     */
    public function deleted(ComponentSerial $componentSerial)
    {
        $componentSerial->component?->syncQtyFromSerials();
    }

    /**
     * Handle the ComponentSerial "restored" event.
     *
     * @return void
     */
    public function restored(ComponentSerial $componentSerial)
    {
        $componentSerial->component?->syncQtyFromSerials();
    }
}