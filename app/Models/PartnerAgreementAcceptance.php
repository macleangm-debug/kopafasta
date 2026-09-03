<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAgreementAcceptance extends Model
{
    protected $fillable = [
        'partner_id',
        'partner_type',
        'agreement_key',
        'agreement_version',
        'policy_version',
        'locale',
        'rendered_text',
        'content_hash',
        'settings_snapshot',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'settings_snapshot' => 'array',
            'accepted_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
