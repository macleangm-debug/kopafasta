<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValuationAssignment extends Model
{
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'loan_application_id',
        'vendor_id',
        'vendor_task_id',
        'status',
        'market_value',
        'forced_sale_value',
        'notes',
        'assigned_by',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'market_value'      => 'decimal:2',
            'forced_sale_value' => 'decimal:2',
            'assigned_at'       => 'datetime',
            'completed_at'      => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorTask(): BelongsTo
    {
        return $this->belongsTo(VendorTask::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
