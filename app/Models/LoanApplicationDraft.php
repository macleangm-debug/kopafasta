<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationDraft extends Model
{
    protected $fillable = [
        'customer_id',
        'loan_product_id',
        'asset_reservation_id',
        'phase',
        'step',
        'payload',
        'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'   => 'array',
            'saved_at'  => 'datetime',
            'step'      => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
