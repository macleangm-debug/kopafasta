<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationRegion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(LocationCountry::class, 'country_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(LocationDistrict::class, 'region_id');
    }
}
