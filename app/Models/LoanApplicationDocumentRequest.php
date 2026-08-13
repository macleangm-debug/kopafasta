<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanApplicationDocumentRequest extends Model
{
    public const STATUSES = ['pending', 'uploaded', 'satisfied', 'rejected'];

    public const TYPES = ['document', 'clarification'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'satisfied_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function subjectCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'subject_customer_id');
    }

    public function uploadedByCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'uploaded_by_customer_id');
    }

    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(LoanGroupMember::class, 'loan_group_member_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function satisfier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'satisfied_by');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(CustomerDocument::class, 'loan_application_document_request_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'uploaded', 'rejected'], true);
    }

    public function needsBorrowerAction(): bool
    {
        return in_array($this->status, ['pending', 'rejected'], true);
    }

    /** Human label: "Amina Juma · Leader" / "John Doe · Guarantor" / "Borrower". */
    public function subjectRoleLabel(?array $groupReview = null): string
    {
        $kind = (string) ($this->subject_kind ?? 'borrower');
        $name = $this->subjectCustomer?->full_name
            ?? $this->groupMember?->customer?->full_name
            ?? null;

        if ($kind === 'member') {
            $member = null;
            if ($this->loan_group_member_id && is_array($groupReview)) {
                $member = collect($groupReview['members'] ?? [])->firstWhere('id', $this->loan_group_member_id);
            }
            $role = strtolower((string) ($member['role'] ?? $this->groupMember?->role ?? 'member'));
            $roleLabel = $role === 'leader' ? 'Leader' : 'Member';
            $name = $name
                ?? (is_array($member) ? ($member['name'] ?? null) : null)
                ?? 'Group member';

            return trim($name).' · '.$roleLabel;
        }

        if ($kind === 'guarantor') {
            return trim(($name ?: 'Guarantor')).' · Guarantor';
        }

        $name = $name ?: ($this->application?->customer?->full_name ?? 'Borrower');
        $isGroup = (bool) ($this->application?->loanGroup);

        return trim($name).' · '.($isGroup ? 'Leader' : 'Borrower');
    }
}
