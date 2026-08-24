<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlusMoneyEntry extends Model
{
    protected $table = 'plus_money_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'inflow' => 'decimal:2',
            'outflow' => 'decimal:2',
        ];
    }

    protected function moneyIn(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['inflow'] ?? 0,
            set: fn ($value) => ['inflow' => $value],
        );
    }

    protected function moneyOut(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['outflow'] ?? 0,
            set: fn ($value) => ['outflow' => $value],
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
