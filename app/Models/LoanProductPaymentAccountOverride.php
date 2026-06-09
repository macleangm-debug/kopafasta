<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductPaymentAccountOverride extends Model
{
    protected $fillable = [
        'loan_product_id',
        'payment_type',
        'payment_method',
        'bank_account_id',
        'mobile_money_account_id',
        'payment_instructions',
    ];

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function mobileMoneyAccount(): BelongsTo
    {
        return $this->belongsTo(MobileMoneyAccount::class);
    }
}
