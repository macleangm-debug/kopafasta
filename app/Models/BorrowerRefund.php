<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerRefund extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AWAITING_PAYOUT = 'awaiting_payout';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id',
        'loan_id',
        'asset_auction_settlement_id',
        'reference',
        'amount',
        'currency',
        'status',
        'payout_channel',
        'payout_phone',
        'payout_account_name',
        'payout_account_number',
        'payout_provider',
        'details_submitted_at',
        'paid_at',
        'paid_by',
        'payment_reference',
        'notes',
        'accrual_journal_entry_id',
        'payout_journal_entry_id',
        'accrual_posted_at',
        'payout_posted_at',
        'disbursement_status',
        'disbursement_reference',
        'disbursement_dispatched_at',
        'disbursement_error',
        'disbursement_mobile_money_account_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'               => 'decimal:2',
            'details_submitted_at' => 'datetime',
            'paid_at'              => 'datetime',
            'accrual_posted_at'    => 'datetime',
            'payout_posted_at'     => 'datetime',
            'disbursement_dispatched_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(AssetAuctionSettlement::class, 'asset_auction_settlement_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function accrualJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'accrual_journal_entry_id');
    }

    public function payoutJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payout_journal_entry_id');
    }

    public function disbursementMobileMoneyAccount(): BelongsTo
    {
        return $this->belongsTo(MobileMoneyAccount::class, 'disbursement_mobile_money_account_id');
    }

    public function isPayable(): bool
    {
        return $this->status === self::STATUS_AWAITING_PAYOUT;
    }

    public function needsPayoutDetails(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
