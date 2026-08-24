<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerGradeEvaluation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'component_scores' => 'array',
            'facts' => 'array',
            'gates_passed' => 'array',
            'gates_failed' => 'array',
            'integrity_signals' => 'array',
            'next_review_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
