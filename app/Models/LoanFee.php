<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanFee extends Model
{
    protected $casts = [
        'rate_or_amount' => 'decimal:4',
        'computed_amount' => 'decimal:2',
        'deducted_from_principal' => 'boolean',
        'charged_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function chargesFee(): BelongsTo
    {
        return $this->belongsTo(ChargesFee::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }
}
