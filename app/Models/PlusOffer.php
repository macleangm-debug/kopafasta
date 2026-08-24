<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlusOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'plus_only' => 'boolean',
            'active' => 'boolean',
            'eligible_grades' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
