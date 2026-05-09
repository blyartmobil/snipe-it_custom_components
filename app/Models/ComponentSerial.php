<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComponentSerial extends SnipeModel
{
    use HasFactory, SoftDeletes, Searchable;

    protected $table = 'component_serials';

    protected $fillable = [
        'component_id',
        'serial',
        'status',
        'asset_id',
        'created_by',
        'notes',
        'checkout_at',
    ];

    protected $casts = [
        'checkout_at' => 'datetime',
    ];

    const STATUS_AVAILABLE   = 'available';
    const STATUS_CHECKED_OUT = 'checked_out';
    const STATUS_DEFECTIVE   = 'defective';
    const STATUS_RETIRED     = 'retired';

    /** @return BelongsTo */
    public function component()
    {
        return $this->belongsTo(Component::class, 'component_id')->withTrashed();
    }

    /** @return BelongsTo */
    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id')->withTrashed();
    }

    /** @return BelongsTo */
    public function adminuser()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isCheckedOut(): bool
    {
        return $this->status === self::STATUS_CHECKED_OUT;
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeCheckedOut($query)
    {
        return $query->where('status', self::STATUS_CHECKED_OUT);
    }

    /**
     * Checkout this serial to an asset.
     */
    public function checkout(int $assetId, ?string $note = null): void
    {
        $this->update([
            'status'      => self::STATUS_CHECKED_OUT,
            'asset_id'    => $assetId,
            'checkout_at' => now(),
            'notes'       => $note ?? $this->notes,
        ]);
    }

    /**
     * Checkin this serial from an asset.
     */
    public function checkin(?string $note = null): void
    {
        $this->update([
            'status'      => self::STATUS_AVAILABLE,
            'asset_id'    => null,
            'checkout_at' => null,
            'notes'       => $note ?? $this->notes,
        ]);
    }
}
