<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Helpers\Helper;
use Illuminate\Http\Resources\Json\JsonResource;

class ComponentSerialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $asset = $this->asset;

        return [
            'id'                => (int) $this->id,
            'component_id'      => (int) $this->component_id,
            'serial'            => e($this->serial),
            'status'            => $this->status,
            'asset'             => $asset
                ? array_merge(
                    (new \App\Http\Transformers\AssetsTransformer)->transformAssetCompact($asset),
                    ['type' => 'asset']
                )
                : null,
            'notes'             => $this->notes
                ? Helper::parseEscapedMarkedownInline($this->notes)
                : null,
            'checkout_at'       => Helper::getFormattedDateObject($this->checkout_at, 'datetime'),
            'created_by'        => $this->whenLoaded('adminuser', fn() => [
                'id'   => (int) $this->adminuser->id,
                'name' => e($this->adminuser->display_name),
            ]),
            'created_at'        => Helper::getFormattedDateObject($this->created_at, 'datetime'),
            'updated_at'        => Helper::getFormattedDateObject($this->updated_at, 'datetime'),
            'available_actions' => [
                'checkin'      => $this->isCheckedOut(),
                'status_change' => true,
                'delete'       => ! $this->isCheckedOut(),
            ],
        ];
    }
}