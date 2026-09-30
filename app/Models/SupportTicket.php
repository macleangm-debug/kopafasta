<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'first_response_at' => 'datetime',
            'assigned_at' => 'datetime',
            'escalated_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_warned_at' => 'datetime',
        ];
    }

    /** Alias used by older ops copy — always the snapshotted SLA clock. */
    public function getDueAtAttribute(): mixed
    {
        return $this->sla_due_at;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function rating(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SupportTicketRating::class);
    }

    /** Public ticket reference — prefer KPF-TKT; never substitute conversation SUP-* ids. */
    public function publicNumber(): string
    {
        $n = trim((string) ($this->ticket_number ?? ''));
        if ($n !== '') {
            return $n;
        }

        return 'KPF-TKT-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function contactLabel(): string
    {
        if ($this->customer) {
            return trim($this->customer->first_name.' '.$this->customer->last_name) ?: ('Member #'.$this->customer_id);
        }

        return trim((string) $this->guest_name) !== ''
            ? (string) $this->guest_name
            : ($this->guest_email ?: ($this->guest_phone ?: 'Guest'));
    }
}
