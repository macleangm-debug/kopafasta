<?php

namespace App\Models;

use App\Models\Concerns\MapsLegacyPartnerId;
use App\Models\Concerns\MapsLegacyPartnerTaskId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPayment extends Model
{
    use MapsLegacyPartnerId;
    use MapsLegacyPartnerTaskId;

    protected $table = 'partner_payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'paid_at'      => 'datetime',
            'approved_at'  => 'datetime',
            'disputed_at'  => 'datetime',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(PartnerTask::class, 'partner_task_id');
    }

    public function partnerSettlement(): BelongsTo
    {
        return $this->belongsTo(PartnerSettlement::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
