<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanAgreement extends Model
{
    protected $casts = [
        'snapshot'         => 'array',
        'sent_at'          => 'datetime',
        'signed_at'        => 'datetime',
        'expires_at'       => 'datetime',
        'otp_sent_at'      => 'datetime',
        'otp_expires_at'   => 'datetime',
    ];

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed' && $this->signed_at !== null;
    }

    public function isOfferExpired(): bool
    {
        if ($this->document_type !== 'offer_letter') {
            return false;
        }

        if ($this->isSigned()) {
            return false;
        }

        if ($this->status === 'expired') {
            return true;
        }

        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }
}
