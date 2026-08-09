<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetReservation extends Model
{
    public const STATUSES = [
        'application_started',
        'viewing_scheduled',
        'viewing_completed',
        'interest_confirmed',
        'reservation_fee_paid',
        'deposit_paid',
        'application_submitted',
        'approved',
        'post_approval_fees_paid',
        'gps_installation',
        'insurance_active',
        'registration_complete',
        'released',
        'cancelled',
    ];

    protected $fillable = [
        'customer_id',
        'marketplace_asset_id',
        'loan_application_id',
        'status',
        'viewing_date',
        'viewing_time',
        'reservation_fee_amount',
        'reservation_fee_status',
        'deposit_amount',
        'deposit_status',
        'viewing_completed_at',
        'released_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'viewing_date'           => 'date',
            'reservation_fee_amount' => 'decimal:2',
            'deposit_amount'         => 'decimal:2',
            'viewing_completed_at'   => 'datetime',
            'released_at'            => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAsset::class, 'marketplace_asset_id');
    }

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function stepIndex(): int
    {
        return match ($this->status) {
            'application_started' => 1,
            'viewing_scheduled' => 2,
            'viewing_completed' => 3,
            'interest_confirmed' => 4,
            'reservation_fee_paid' => 5,
            'deposit_paid' => 6,
            'application_submitted', 'approved', 'post_approval_fees_paid', 'gps_installation', 'insurance_active', 'registration_complete' => 7,
            'released' => 8,
            default => 0,
        };
    }
}
