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
            'paid_at'      => 'datetime',
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

    public function requestNumber(): string
    {
        return filled($this->request_number ?? null)
            ? (string) $this->request_number
            : 'WDR-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function payoutPaymentId(): string
    {
        return filled($this->payment_reference ?? null)
            ? (string) $this->payment_reference
            : '—';
    }

    public function publicStatus(): string
    {
        return match ((string) $this->status) {
            'pending' => 'review',
            'approved' => 'processing',
            'paid' => 'paid',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            default => (string) $this->status,
        };
    }

    public function publicReason(): ?string
    {
        if (! in_array((string) $this->status, ['rejected', 'cancelled'], true)) {
            return null;
        }

        $notes = (string) ($this->notes ?? '');
        if (preg_match('/Rejected:\s*(.+)$/m', $notes, $match)) {
            return trim($match[1]);
        }

        return filled($notes) ? trim($notes) : null;
    }
}
