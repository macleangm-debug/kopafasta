<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAuctionSettlement extends Model
{
    protected $fillable = [
        'loan_id',
        'arrear_case_id',
        'recovery_assignment_id',
        'outstanding_before',
        'recovery_costs',
        'auction_proceeds',
        'outstanding_applied',
        'recovery_applied',
        'borrower_refund',
        'remaining_balance',
        'loan_closed',
        'repayment_id',
        'recorded_by',
        'notes',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_before'  => 'decimal:2',
            'recovery_costs'      => 'decimal:2',
            'auction_proceeds'    => 'decimal:2',
            'outstanding_applied' => 'decimal:2',
            'recovery_applied'    => 'decimal:2',
            'borrower_refund'     => 'decimal:2',
            'remaining_balance'   => 'decimal:2',
            'loan_closed'         => 'boolean',
            'settled_at'          => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function arrearCase(): BelongsTo
    {
        return $this->belongsTo(ArrearCase::class);
    }

    public function recoveryAssignment(): BelongsTo
    {
        return $this->belongsTo(RecoveryAssignment::class);
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(Repayment::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
