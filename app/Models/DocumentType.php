<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'applies_to',
        'is_active',
        'expires',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires' => 'boolean',
        ];
    }

    public function localizedName(?string $locale = null): string
    {
        return app(\App\Services\ApplicationDocumentRequestService::class)
            ->localizedDocumentTypeName($this->code, $this->name, $locale);
    }

    public function requiresExpiry(): bool
    {
        return (bool) $this->expires;
    }
}
