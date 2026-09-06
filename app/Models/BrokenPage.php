<?php

namespace App\Models;

use App\Services\BrokenPageClassifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrokenPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'status' => 'integer',
            'occurrence_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function isNeedsAttention(): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $category = $this->category ?: 'genuine_defect';

        return in_array($category, BrokenPageClassifier::NEEDS_ATTENTION, true);
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereNull('resolved_at')
            ->where(function (Builder $inner): void {
                $inner->whereIn('category', BrokenPageClassifier::NEEDS_ATTENTION)
                    ->orWhere(function (Builder $uncategorized): void {
                        $uncategorized->whereNull('category')
                            ->whereIn('status', [500, 503]);
                    });
            });
    }
}
