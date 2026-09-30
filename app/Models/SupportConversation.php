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
        ];
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

    /** Public reference e.g. KPF-CNV-000042 (routes still use DB id). */
    public function publicNumber(): string
    {
        $n = trim((string) ($this->conversation_number ?? ''));

        return $n !== '' ? $n : 'KPF-CNV-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
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
