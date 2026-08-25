<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlusMonthlyReport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'payload' => 'array',
            'notified_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
