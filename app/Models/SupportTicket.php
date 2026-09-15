<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function contactLabel(): string
    {
        if ($this->customer) {
            return trim($this->customer->first_name.' '.$this->customer->last_name) ?: ('Customer #'.$this->customer_id);
        }

        return trim((string) $this->guest_name) !== ''
            ? (string) $this->guest_name
            : ($this->guest_email ?: ($this->guest_phone ?: 'Guest'));
    }
}
