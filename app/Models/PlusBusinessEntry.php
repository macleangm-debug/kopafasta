<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlusBusinessEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'sold' => 'decimal:2',
            'spent' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
