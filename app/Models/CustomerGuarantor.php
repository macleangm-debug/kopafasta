<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerGuarantor extends Model
{
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(Guarantor::class);
    }

    public function displayName(): string
    {
        $guarantor = $this->guarantor;

        if ($guarantor) {
            return trim(($guarantor->first_name ?? '').' '.($guarantor->last_name ?? '')) ?: __('borrower.application.guarantor_member');
        }

        return __('borrower.application.guarantor_member');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    /** Alias used by eager-loads / older call sites. */
    public function loanApplication(): BelongsTo
    {
        return $this->application();
    }

    public function invitation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GuarantorInvitation::class);
    }
}
