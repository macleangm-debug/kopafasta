<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRequest extends Model
{
    protected $fillable = [
        'customer_id', 'vendor_id', 'asset_name', 'description', 'budget', 'preferred_tenure_months',
        'photo_path', 'additional_photos', 'status', 'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'budget'            => 'decimal:2',
            'additional_photos' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
