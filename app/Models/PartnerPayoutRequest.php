<?php

namespace App\Models;

use App\Models\Concerns\MapsLegacyPartnerId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPayoutRequest extends Model
{
    use MapsLegacyPartnerId;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'reviewed_at'  => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->partner();
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
