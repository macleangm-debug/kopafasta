<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuarantorInvitation extends Model
{
    protected $fillable = [
        'customer_id',
        'loan_application_id',
        'customer_guarantor_id',
        'guarantor_customer_id',
        'type',
        'channel',
        'contact',
        'membership_id',
        'invitee_name',
        'requested_amount',
        'requested_tenure_months',
        'token',
        'short_code',
        'status',
        'expires_at',
        'responded_at',
        'response_notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'    => 'datetime',
            'responded_at'  => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function customerGuarantor(): BelongsTo
    {
        return $this->belongsTo(CustomerGuarantor::class);
    }

    public function guarantorCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'guarantor_customer_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->greaterThan($this->expires_at);
    }
}
