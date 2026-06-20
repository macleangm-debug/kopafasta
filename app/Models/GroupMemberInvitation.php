<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMemberInvitation extends Model
{
    protected $fillable = [
        'leader_customer_id',
        'loan_product_id',
        'loan_application_draft_id',
        'customer_id',
        'invitee_first_name',
        'invitee_middle_name',
        'invitee_last_name',
        'invitee_phone',
        'invitee_email',
        'token',
        'short_code',
        'status',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'leader_customer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function displayName(): string
    {
        return trim(collect([
            $this->invitee_first_name,
            $this->invitee_middle_name,
            $this->invitee_last_name,
        ])->filter()->implode(' '));
    }
}
