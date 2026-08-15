<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDocument extends Model
{
    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(LoanProductRequirement::class, 'loan_product_requirement_id');
    }

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(LoanApplicationDocumentRequest::class, 'loan_application_document_request_id');
    }

    public function applicationReviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LoanApplicationDocumentReview::class, 'customer_document_id');
    }

    public function displayName(): string
    {
        if (filled($this->documentType?->name)) {
            return (string) $this->documentType->name;
        }

        if (filled($this->documentRequest?->label)) {
            return (string) $this->documentRequest->label;
        }

        $meta = [];
        if (filled($this->notes)) {
            $decoded = json_decode((string) $this->notes, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (filled($meta['request_label'] ?? null)) {
            return (string) $meta['request_label'];
        }
        if (filled($meta['original_name'] ?? null)) {
            return (string) $meta['original_name'];
        }

        return 'Document';
    }
}
