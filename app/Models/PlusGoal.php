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
            'target_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function progressPercent(): int
    {
        $target = (float) $this->target_amount;
        if ($target <= 0) {
            return 0;
        }

        return (int) min(100, round(((float) $this->saved_amount / $target) * 100));
    }

    public function remaining(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->saved_amount);
    }

    public function isPaused(): bool
    {
        return ($this->status ?? 'active') === 'paused';
    }

    public function kindIcon(): string
    {
        return match ($this->kind) {
            'business', 'stock' => '🏪',
            'school' => '📚',
            'home' => '🏠',
            'vehicle' => '🛵',
            'emergency' => '🛟',
            default => '🎯',
        };
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
