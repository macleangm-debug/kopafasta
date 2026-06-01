<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProductPostApprovalFee extends Model
{
    protected $fillable = [
        'loan_product_id', 'code', 'name', 'fee_type', 'amount', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount'    => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
