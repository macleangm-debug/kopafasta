<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationDistrict extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(LocationRegion::class, 'region_id');
    }

    public function wards(): HasMany
    {
        return $this->hasMany(LocationWard::class, 'district_id');
    }
}
