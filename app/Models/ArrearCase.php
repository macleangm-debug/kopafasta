<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ArrearCase extends Model
{
    protected function casts(): array
    {
        return [
            'amount_in_arrears' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'last_follow_up_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CollectionAction::class);
    }
}
