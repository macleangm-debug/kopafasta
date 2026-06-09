<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAccountMapping extends Model
{
    protected $fillable = [
        'payment_type',
        'payment_method',
        'bank_account_id',
        'mobile_money_account_id',
        'payment_instructions',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function mobileMoneyAccount(): BelongsTo
    {
        return $this->belongsTo(MobileMoneyAccount::class);
    }

    public function typeLabel(): string
    {
        return config("payment_types.types.{$this->payment_type}.label", ucfirst(str_replace('_', ' ', $this->payment_type)));
    }

    public function methodLabel(): string
    {
        return config("payment_types.methods.{$this->payment_method}.label", ucfirst(str_replace('_', ' ', $this->payment_method)));
    }
}
