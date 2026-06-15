<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAsset extends Model
{
    protected $fillable = [
        'customer_id',
        'asset_type',
        'label',
        'description',
        'registration_number',
        'estimated_value',
        'photo_paths',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'photo_paths'     => 'array',
            'metadata'        => 'array',
            'is_active'       => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            'vehicle'   => 'Vehicle',
            'house'     => 'House',
            'land'      => 'Land',
            'equipment' => 'Equipment',
        ];
    }
}
