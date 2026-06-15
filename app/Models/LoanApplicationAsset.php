<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationAsset extends Model
{
    protected $fillable = [
        'loan_application_id',
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
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function isMovableAsset(): bool
    {
        return in_array($this->asset_type, ['motorcycle', 'saloon_car', 'suv', 'truck', 'heavy_machinery'], true);
    }
}
