<?php

namespace App\Models;

use App\Models\Concerns\MapsLegacyPartnerId;
use App\Models\Concerns\MapsLegacyPartnerTaskId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryAssignment extends Model
{
    use MapsLegacyPartnerId;
    use MapsLegacyPartnerTaskId;

    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ESCALATED = 'escalated';

    protected $fillable = [
        'arrear_case_id',
        'vendor_id',
        'partner_type',
        'status',
        'original_outstanding',
        'commission_percent',
        'company_markup_percent',
        'recovery_charge',
        'commission_earned',
        'commission_paid',
        'sla_due_at',
        'assigned_by',
        'assigned_at',
        'completed_at',
        'outcome',
        'notes',
        'vendor_task_id',
    ];

    protected function casts(): array
    {
        return [
            'original_outstanding'   => 'decimal:2',
            'commission_percent'     => 'decimal:2',
            'company_markup_percent' => 'decimal:2',
            'recovery_charge'        => 'decimal:2',
            'commission_earned'      => 'decimal:2',
            'commission_paid'        => 'decimal:2',
            'sla_due_at'             => 'datetime',
            'assigned_at'            => 'datetime',
            'completed_at'           => 'datetime',
        ];
    }

    public function arrearCase(): BelongsTo
    {
        return $this->belongsTo(ArrearCase::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'partner_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function vendorTask(): BelongsTo
    {
        return $this->belongsTo(PartnerTask::class, 'partner_task_id');
    }

    public function partnerTask(): BelongsTo
    {
        return $this->vendorTask();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS], true);
    }

    public function slaBreached(): bool
    {
        return $this->sla_due_at
            && $this->sla_due_at->isPast()
            && $this->isOpen();
    }
}
