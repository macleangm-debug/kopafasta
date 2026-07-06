<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileSectionDefinition extends Model
{
    protected $fillable = [
        'key',
        'icon',
        'name_en',
        'name_sw',
        'description_en',
        'description_sw',
        'is_required',
        'input_type',
        'validation_rules',
        'display_order',
        'required_before_loan',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_required'           => 'boolean',
            'required_before_loan'  => 'boolean',
            'is_active'             => 'boolean',
            'validation_rules'      => 'array',
            'metadata'              => 'array',
        ];
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'sw' && filled($this->name_sw)) {
            return $this->name_sw;
        }

        return $this->name_en;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'sw' && filled($this->description_sw)) {
            return $this->description_sw;
        }

        return $this->description_en;
    }
}
