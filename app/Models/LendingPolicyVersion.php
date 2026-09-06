<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LendingPolicyVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'approved_at' => 'datetime',
            'next_review_at' => 'datetime',
            'snapshot' => 'array',
            'warnings' => 'array',
        ];
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
