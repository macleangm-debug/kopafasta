<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportGuest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'first_contact_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'converted_at' => 'datetime',
        'contact_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(SupportConversation::class, 'guest_phone', 'phone');
    }

    public function isActiveGuest(): bool
    {
        return (string) $this->registration_status === 'guest'
            && blank($this->customer_id)
            && blank($this->converted_at);
    }

    public function displayName(): string
    {
        return trim($this->first_name.' '.$this->last_name) ?: 'Guest';
    }
}
