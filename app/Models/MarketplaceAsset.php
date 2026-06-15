<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceAsset extends Model
{
    protected $fillable = [
        'slug', 'category', 'title', 'serial_number', 'chassis_number', 'engine_number',
        'insurance_policy_number', 'description', 'supplier_name', 'vendor_id',
        'asset_value', 'supplier_deposit', 'deposit_markup_percent', 'customer_deposit',
        'weekly_installment', 'max_tenure_months', 'photos', 'is_active', 'availability_status',
    ];

    protected function casts(): array
    {
        return [
            'asset_value'            => 'decimal:2',
            'supplier_deposit'       => 'decimal:2',
            'deposit_markup_percent' => 'decimal:2',
            'customer_deposit'       => 'decimal:2',
            'weekly_installment'     => 'decimal:2',
            'photos'                 => 'array',
            'is_active'              => 'boolean',
        ];
    }

    public function computeCustomerDeposit(): float
    {
        return app(AssetLendingService::class)->computeCustomerDeposit($this);
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(AssetReservation::class);
    }

    public function isAvailable(): bool
    {
        return ($this->availability_status ?? 'available') === 'available';
    }

    public function lock(): void
    {
        $this->update(['availability_status' => 'locked']);
    }

    public function unlock(): void
    {
        $this->update(['availability_status' => 'available']);
    }
}
