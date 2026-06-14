<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDisbursementAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'account_name',
        'mobile_provider',
        'mobile_number',
        'bank_name',
        'account_number',
        'bank_branch',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isMobile(): bool
    {
        return $this->type === 'mobile_money';
    }

    public function isBank(): bool
    {
        return $this->type === 'bank';
    }
}
