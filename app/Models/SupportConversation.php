<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'needs_human'          => 'boolean',
            'last_message_at'      => 'datetime',
            'rated_at'             => 'datetime',
            'waiting_since'        => 'datetime',
            'accepted_at'          => 'datetime',
            'resolved_at'          => 'datetime',
            'closed_at'            => 'datetime',
            'rating_requested_at'  => 'datetime',
            'automation_meta'      => 'array',
        ];
    }

    public function handlingLabel(?string $locale = null): string
    {
        return app(\App\Services\Support\SupportAutomationService::class)
            ->handlingLabel((string) ($this->handling_state ?: ''), $locale);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function awaitsRating(): bool
    {
        return in_array((string) $this->status, ['resolved', 'closed'], true)
            && $this->rating_requested_at
            && ! $this->rating;
    }

    /** Public reference e.g. KPF-CNV-A7K4Q2 (routes still use DB id). Never expose bare #id. */
    public function publicNumber(): string
    {
        $n = trim((string) ($this->conversation_number ?? ''));
        if ($n !== '') {
            return $n;
        }

        // Soft display fallback — prefer regenerating alphanumeric via ensureAlphanumericReference().
        return 'KPF-CNV-'.strtoupper(substr(hash('crc32b', 'cnv:'.$this->id), 0, 6));
    }

    /** True when stored number is missing or legacy sequential padded id. */
    public function needsAlphanumericReference(): bool
    {
        $n = trim((string) ($this->conversation_number ?? ''));
        if ($n === '') {
            return true;
        }
        $padded = 'KPF-CNV-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);

        return $n === $padded || (bool) preg_match('/^KPF-CNV-\d{4,}$/', $n);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'support_conversation_id');
    }
}
