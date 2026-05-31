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
}
