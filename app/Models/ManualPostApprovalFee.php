<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPostApprovalFee extends Model
{
    protected $fillable = [
        'loan_application_id',
        'description',
        'partner_cost',
        'markup_percent',
        'borrower_amount',
        'status',
        'created_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'partner_cost'    => 'decimal:2',
            'markup_percent'  => 'decimal:2',
            'borrower_amount' => 'decimal:2',
            'paid_at'         => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
