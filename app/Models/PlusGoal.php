<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlusGoal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'saved_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null
            || (float) $this->saved_amount >= (float) $this->target_amount;
    }
}
