<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class RestructureRequest extends Model
{
    protected function casts(): array
    {
        return [
            'new_interest_rate' => 'decimal:4',
            'approved_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
