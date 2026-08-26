<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingPersona extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'traits' => 'array',
            'defaults' => 'array',
            'restricted' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
