<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanApplicationDocumentReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CustomerDocument::class, 'customer_document_id');
    }

    public function subjectCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'subject_customer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isVerified(): bool
    {
        return in_array($this->status, ['verified', 'approved'], true);
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function failReasonLabel(): string
    {
        if ($this->fail_reason_code === 'custom' && filled($this->fail_reason_custom)) {
            return (string) $this->fail_reason_custom;
        }

        $map = config('application_document_review.fail_reasons', []);

        return (string) ($map[$this->fail_reason_code] ?? $this->fail_reason_code ?? '—');
    }
}
