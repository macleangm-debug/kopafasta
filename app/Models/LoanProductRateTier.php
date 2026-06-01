<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductRateTier extends Model
{
    protected $fillable = [
        'loan_product_id', 'min_amount', 'max_amount', 'monthly_rate', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_amount'   => 'decimal:2',
            'max_amount'   => 'decimal:2',
            'monthly_rate' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
