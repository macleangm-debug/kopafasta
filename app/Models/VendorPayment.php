<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'paid_at'     => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(VendorTask::class, 'vendor_task_id');
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
