<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerApplication extends Model
{
    protected $fillable = [
        'type',
        'partner_category',
        'requested_roles',
        'applicant_category',
        'full_name',
        'email',
        'phone',
        'business_name',
        'legal_name',
        'registration_number',
        'tin',
        'region',
        'coverage_regions',
        'message',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'partner_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'coverage_regions' => 'array',
            'requested_roles' => 'array',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnerApplicationDocument::class);
    }

    public function resolvedCategory(): string
    {
        if (filled($this->partner_category)) {
            return (string) $this->partner_category;
        }

        return $this->type === 'affiliate' ? 'affiliate' : (string) $this->type;
    }

    public function categoryLabel(): string
    {
        return app(\App\Services\PartnerEnrollmentService::class)->categoryLabel($this->resolvedCategory());
    }
}
