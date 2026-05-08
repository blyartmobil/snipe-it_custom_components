<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Component;
use App\Models\ComponentSerial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ComponentsTransformer
{
    public function transformComponents(Collection $components, $total)
    {
        $array = [];
        foreach ($components as $component) {
            $array[] = self::transformComponent($component);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformComponent(Component $component)
    {
        $array = [
            'id' => (int) $component->id,
            'name' => e($component->name),
            'image' => ($component->image) ? Storage::disk('public')->url('components/'.e($component->image)) : null,
            'serial' => ($component->serial) ? e($component->serial) : null,
            'location' => ($component->location) ? [
                'id' => (int) $component->location->id,
                'name' => e($component->location->name),
                'tag_color' => $component->location->tag_color ? e($component->location->tag_color) : null,
            ] : null,
            'qty' => ($component->qty != '') ? (int) $component->qty : null,
            'min_amt' => ($component->min_amt != '') ? (int) $component->min_amt : null,
            'category' => ($component->category) ? [
                'id' => (int) $component->category->id,
                'name' => e($component->category->name),
                'tag_color' => $component->category->tag_color ? e($component->category->tag_color) : null,
            ] : null,
            'supplier' => ($component->supplier) ? [
                'id' => $component->supplier->id,
                'name' => e($component->supplier->name),
                'tag_color' => $component->supplier->tag_color ? e($component->supplier->tag_color) : null,
            ] : null,
            'manufacturer' => ($component->manufacturer) ? [
                'id' => $component->manufacturer->id,
                'name' => e($component->manufacturer->name),
                'tag_color' => $component->manufacturer->tag_color ? e($component->manufacturer->tag_color) : null,
            ] : null,
            'model_number' => ($component->model_number) ? e($component->model_number) : null,
            'order_number' => e($component->order_number),
            'purchase_date' => Helper::getFormattedDateObject($component->purchase_date, 'date'),
            'purchase_cost' => Helper::formatCurrencyOutput($component->purchase_cost),
            'total_cost' => Helper::formatCurrencyOutput($component->totalCostSum()),
            'remaining' => (int) $component->numRemaining(),
            'percent_remaining' => round($component->percentRemaining()),
            'company' => ($component->company) ? [
                'id' => (int) $component->company->id,
                'name' => e($component->company->name),
                'tag_color' => $component->company->tag_color ? e($component->company->tag_color) : null,
            ] : null,
            'notes' => ($component->notes) ? Helper::parseEscapedMarkedownInline($component->notes) : null,
            'created_by' => ($component->adminuser) ? [
                'id' => (int) $component->adminuser->id,
                'name' => e($component->adminuser->display_name),
            ] : null,
            'created_at' => Helper::getFormattedDateObject($component->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($component->updated_at, 'datetime'),
            'user_can_checkout' => ($component->numRemaining() > 0) ? 1 : 0,
            'total_serials' => (int) $component->serials()->count(),
            'available_serials' => (int) $component->availableSerials()->count(),
            'checked_out_serials' => (int) $component->checkedOutSerials()->count(),
        ];

        $permissions_array['available_actions'] = [
            'checkout' => Gate::allows('checkout', Component::class),
            'checkin' => Gate::allows('checkin', Component::class),
            'update' => Gate::allows('update', Component::class),
            'clone' => Gate::allows('create', Component::class),
            'delete' => $component->isDeletable(),
        ];
        $array += $permissions_array;

        return $array;
    }

    public function transformCheckedoutComponents(Collection $components_assets, $total)
    {
        $array = [];
        foreach ($components_assets as $serial) {
            $asset = $serial->asset;
            $assigned_asset = null;
            if ($asset) {
                $compact = (new AssetsTransformer)->transformAssetCompact($asset);
                $assigned_asset = array_merge($compact, ['type' => 'asset']);
            }
            $array[] = [
                'serial_id' => (int) $serial->id,
                'serial_number' => e($serial->serial),
                'assigned_asset' => $assigned_asset,
                'note' => ($serial->notes) ? e($serial->notes) : null,
                'created_by' => $serial->adminuser ? [
                    'id' => (int) $serial->adminuser->id,
                    'name' => e($serial->adminuser->display_name),
                ] : null,
                'created_at' => Helper::getFormattedDateObject($serial->created_at, 'datetime'),
                'available_actions' => ['checkin' => true],
            ];
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformAssignedTo($componentCheckout)
    {
        return (new AssetsTransformer)->transformAssetCompact($componentCheckout);
    }

    /**
     * Transform a single ComponentSerial for API responses.
     */
    public function transformSerial(ComponentSerial $serial)
    {
        return [
            'id' => (int) $serial->id,
            'component_id' => (int) $serial->component_id,
            'serial' => e($serial->serial),
            'status' => $serial->status,
            'asset' => $serial->asset ? array_merge((new AssetsTransformer)->transformAssetCompact($serial->asset), ['type' => 'asset']) : null,
            'notes' => ($serial->notes) ? Helper::parseEscapedMarkedownInline($serial->notes) : null,
            'checkout_at' => Helper::getFormattedDateObject($serial->checkout_at, 'datetime'),
            'created_at' => Helper::getFormattedDateObject($serial->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($serial->updated_at, 'datetime'),
            'available_actions' => [
                'checkin' => $serial->isCheckedOut(),
                'status_change' => true,
                'delete' => ! $serial->isCheckedOut(),
            ],
        ];
    }

    /**
     * Transform a collection of serials for paginated API responses.
     */
    public function transformSerials(Collection $serials, $total)
    {
        $array = [];
        foreach ($serials as $serial) {
            $array[] = $this->transformSerial($serial);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }
}
