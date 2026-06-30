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
        'draft_reference',
        'invitation_reason',
        'group_name',
        'group_purpose',
        'amount_per_member',
        'requested_tenure_months',
        'repayment_cadence',
        'replaces_loan_group_member_id',
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
        'link_opened_at',
        'registration_started_at',
        'membership_id',
        'member_signer_name',
        'member_signature_data',
        'member_signed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'              => 'datetime',
            'responded_at'            => 'datetime',
            'link_opened_at'          => 'datetime',
            'registration_started_at' => 'datetime',
            'member_signed_at'        => 'datetime',
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

    public function draft(): BelongsTo
    {
        return $this->belongsTo(LoanApplicationDraft::class, 'loan_application_draft_id');
    }

    public function replacesMember(): BelongsTo
    {
        return $this->belongsTo(LoanGroupMember::class, 'replaces_loan_group_member_id');
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
