<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomerPayment extends Model
{
    protected $fillable = [
        'reference',
        'customer_id',
        'payment_type',
        'payment_method',
        'provider',
        'provider_ref',
        'provider_meta',
        'amount',
        'currency',
        'status',
        'bank_account_id',
        'mobile_money_account_id',
        'mobile_number',
        'payment_instructions',
        'proof_path',
        'proof_original_name',
        'verification_notes',
        'verified_by',
        'verified_at',
        'paid_at',
        'payment_date',
        'journal_entry_id',
        'source_type',
        'source_id',
        'loan_id',
        'loan_product_id',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'provider_meta' => 'array',
        'verified_at'   => 'datetime',
        'paid_at'       => 'datetime',
        'payment_date'  => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function mobileMoneyAccount(): BelongsTo
    {
        return $this->belongsTo(MobileMoneyAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        $key = "borrower.payment_types.{$this->payment_type}";
        $translated = __($key);
        if ($translated !== $key) {
            return $translated;
        }

        return config("payment_types.types.{$this->payment_type}.label", ucfirst(str_replace('_', ' ', $this->payment_type)));
    }

    public function methodLabel(): string
    {
        $key = "borrower.payment_methods.{$this->payment_method}";
        $translated = __($key);
        if ($translated !== $key) {
            return $translated;
        }

        return config("payment_types.methods.{$this->payment_method}.label", ucfirst(str_replace('_', ' ', $this->payment_method)));
    }

    public function methodShortLabel(): string
    {
        $key = "borrower.payment_methods.{$this->payment_method}_short";
        $translated = __($key);
        if ($translated !== $key) {
            return $translated;
        }

        return config("payment_types.methods.{$this->payment_method}.short", $this->methodLabel());
    }

    public function statusLabel(): string
    {
        $key = "borrower.payment_statuses.{$this->status}";
        $translated = __($key);
        if ($translated !== $key) {
            return $translated;
        }

        return config("payment_types.statuses.{$this->status}", ucfirst(str_replace('_', ' ', $this->status)));
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['awaiting_payment', 'pending_verification', 'clarification_requested', 'processing'], true);
    }

    public function awaitsCollection(): bool
    {
        return $this->status === 'awaiting_payment' && $this->payment_method === 'mobile_money';
    }

    public function isPayInWaiting(): bool
    {
        return $this->status === 'processing'
            && ($this->provider === 'payin' || $this->payment_type === 'insurance_premium');
    }

    public function isVerified(): bool
    {
        return in_array($this->status, ['verified', 'paid'], true);
    }

    public function hasProof(): bool
    {
        return filled($this->proof_path);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['awaiting_payment', 'pending_verification', 'clarification_requested', 'processing']);
    }
}
