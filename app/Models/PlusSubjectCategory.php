<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlusSubjectCategory extends Model
{
    protected $guarded = [];

    public function subjects(): HasMany
    {
        return $this->hasMany(PlusSubject::class);
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'sw' ? ($this->title_sw ?: $this->title_en) : $this->title_en;
    }
}
