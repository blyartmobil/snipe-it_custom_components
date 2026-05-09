<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Helpers\Helper;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ComponentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $array = [
            'id'                  => (int) $this->id,
            'name'                => e($this->name),
            'image'               => $this->image ? Storage::disk('public')->url('components/' . e($this->image)) : null,
            'serial'              => $this->serial ? e($this->serial) : null,
            'location'            => $this->whenLoaded('location', fn() => [
                'id'        => (int) $this->location->id,
                'name'      => e($this->location->name),
                'tag_color' => $this->location->tag_color ? e($this->location->tag_color) : null,
            ]),
            'qty'                 => $this->qty !== '' ? (int) $this->qty : null,
            'min_amt'             => $this->min_amt !== '' ? (int) $this->min_amt : null,
            'category'            => $this->whenLoaded('category', fn() => [
                'id'        => (int) $this->category->id,
                'name'      => e($this->category->name),
                'tag_color' => $this->category->tag_color ? e($this->category->tag_color) : null,
            ]),
            'supplier'            => $this->whenLoaded('supplier', fn() => [
                'id'        => $this->supplier->id,
                'name'      => e($this->supplier->name),
                'tag_color' => $this->supplier->tag_color ? e($this->supplier->tag_color) : null,
            ]),
            'manufacturer'        => $this->whenLoaded('manufacturer', fn() => [
                'id'        => $this->manufacturer->id,
                'name'      => e($this->manufacturer->name),
                'tag_color' => $this->manufacturer->tag_color ? e($this->manufacturer->tag_color) : null,
            ]),
            'model_number'        => $this->model_number ? e($this->model_number) : null,
            'order_number'        => e($this->order_number),
            'purchase_date'       => Helper::getFormattedDateObject($this->purchase_date, 'date'),
            'purchase_cost'       => Helper::formatCurrencyOutput($this->purchase_cost),
            'total_cost'          => Helper::formatCurrencyOutput($this->totalCostSum()),
            'remaining'           => (int) $this->numRemaining(),
            'percent_remaining'   => round($this->percentRemaining()),
            'company'             => $this->whenLoaded('company', fn() => [
                'id'        => (int) $this->company->id,
                'name'      => e($this->company->name),
                'tag_color' => $this->company->tag_color ? e($this->company->tag_color) : null,
            ]),
            'notes'               => $this->notes ? Helper::parseEscapedMarkedownInline($this->notes) : null,
            'created_by'          => $this->whenLoaded('adminuser', fn() => [
                'id'   => (int) $this->adminuser->id,
                'name' => e($this->adminuser->display_name),
            ]),
            'created_at'          => Helper::getFormattedDateObject($this->created_at, 'datetime'),
            'updated_at'          => Helper::getFormattedDateObject($this->updated_at, 'datetime'),
            'user_can_checkout'   => $this->numRemaining() > 0 ? 1 : 0,
            'total_serials'       => $this->when(isset($this->serials_count), (int) $this->serials_count, fn() => (int) $this->serials()->count()),
            'available_serials'   => $this->when(isset($this->available_serials_count), (int) $this->available_serials_count, fn() => (int) $this->availableSerials()->count()),
            'checked_out_serials' => $this->when(isset($this->checked_out_serials_count), (int) $this->checked_out_serials_count, fn() => (int) $this->checkedOutSerials()->count()),
            'available_actions'   => [
                'checkout' => Gate::allows('checkout', \App\Models\Component::class) && ($this->numRemaining() > 0),
                'checkin'  => Gate::allows('checkin', \App\Models\Component::class),
                'update'   => Gate::allows('update', \App\Models\Component::class),
                'clone'    => Gate::allows('create', \App\Models\Component::class),
                'delete'   => $this->isDeletable(),
            ],
        ];

        return $array;
    }
}