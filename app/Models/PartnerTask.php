<?php

namespace App\Models;

use App\Models\Concerns\MapsLegacyPartnerId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PartnerTask extends Model
{
    use MapsLegacyPartnerId;

    protected $table = 'partner_tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_at'        => 'datetime',
            'accepted_at'   => 'datetime',
            'started_at'    => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocument::class, 'partner_task_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(PartnerPayment::class, 'partner_task_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->partner();
    }

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** @return array{label: string, tone: string} */
    public function priorityMeta(): array
    {
        if (! $this->due_at) {
            return ['label' => 'Normal', 'tone' => 'gray'];
        }

        $due = $this->due_at;
        $now = now();

        if ($due->isPast() && ! in_array($this->status, ['completed', 'cancelled'], true)) {
            return ['label' => 'Urgent', 'tone' => 'red'];
        }

        if ($due->lte($now->copy()->addDays(2))) {
            return ['label' => 'High', 'tone' => 'amber'];
        }

        if ($due->lte($now->copy()->addDays(7))) {
            return ['label' => 'Normal', 'tone' => 'indigo'];
        }

        return ['label' => 'Low', 'tone' => 'gray'];
    }
}
