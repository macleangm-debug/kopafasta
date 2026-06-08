<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditHistory extends Model
{
    protected $fillable = [
        'customer_id',
        'source',
        'score',
        'risk_grade',
        'payload',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
