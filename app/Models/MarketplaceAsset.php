<?php

namespace App\Models;

use App\Models\Concerns\MapsLegacyPartnerId;
use App\Services\AssetLendingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceAsset extends Model
{
    use MapsLegacyPartnerId;

    protected $fillable = [
        'slug', 'category', 'title', 'serial_number', 'chassis_number', 'engine_number',
        'insurance_policy_number', 'description', 'supplier_name', 'vendor_id',
        'asset_value', 'supplier_deposit', 'deposit_markup_percent', 'customer_deposit',
        'weekly_installment', 'max_tenure_months', 'waiting_period_days', 'photos', 'is_active', 'availability_status',
        'insurance_expires_at',
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
            'insurance_expires_at'   => 'date',
        ];
    }

    public function computeCustomerDeposit(): float
    {
        return app(AssetLendingService::class)->computeCustomerDeposit($this);
    }

    public function depositPercent(): float
    {
        $value = (float) ($this->asset_value ?? 0);
        if ($value <= 0) {
            return 0.0;
        }

        return round(((float) ($this->supplier_deposit ?? 0) / $value) * 100, 2);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::query()
            ->where('id', $value)
            ->orWhere('slug', $value)
            ->first();
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'partner_id');
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
