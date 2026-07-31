<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationAsset extends Model
{
    public const UW_PENDING = 'pending';
    public const UW_ACCEPTED = 'accepted';
    public const UW_DECLINED = 'declined';

    protected $fillable = [
        'loan_application_id',
        'customer_asset_id',
        'asset_type',
        'description',
        'market_value',
        'forced_sale_value',
        'ltv_percent',
        'max_loan_amount',
        'gps_required',
        'valuation_status',
        'valuation_fee_paid_at',
        'valuer_notes',
        'uw_status',
        'uw_notes',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'market_value'          => 'decimal:2',
            'forced_sale_value'     => 'decimal:2',
            'ltv_percent'           => 'decimal:2',
            'max_loan_amount'       => 'decimal:2',
            'gps_required'          => 'boolean',
            'valuation_fee_paid_at' => 'datetime',
            'is_primary'            => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function customerAsset(): BelongsTo
    {
        return $this->belongsTo(CustomerAsset::class);
    }

    public function isMovableAsset(): bool
    {
        return in_array($this->asset_type, ['motorcycle', 'saloon_car', 'suv', 'truck', 'heavy_machinery', 'vehicle'], true);
    }

    public function isDeclined(): bool
    {
        return $this->uw_status === self::UW_DECLINED;
    }

    public function isAccepted(): bool
    {
        return $this->uw_status === self::UW_ACCEPTED;
    }

    public function hasComprehensiveInsurance(): bool
    {
        $meta = $this->customerAsset?->metadata ?? [];

        return filled($meta['insurance_document_path'] ?? null);
    }
}
