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
        'partner_id',
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

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
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
        if ($this->payment_method === 'mobile_money') {
            $operator = data_get($this->provider_meta, 'operator')
                ?? data_get($this->provider_meta, 'raw.operator')
                ?? data_get($this->provider_meta, 'raw.data.operator');
            if (filled($operator)) {
                return $this->formatMobileOperatorLabel((string) $operator);
            }

            return 'Mobile money';
        }

        $key = "borrower.payment_methods.{$this->payment_method}_short";
        $translated = __($key);
        if ($translated !== $key) {
            return $translated;
        }

        return config("payment_types.methods.{$this->payment_method}.short", $this->methodLabel());
    }

    /** Map PayIn / network operator codes to a borrower-facing wallet name. */
    public function formatMobileOperatorLabel(string $operator): string
    {
        $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', trim($operator)));

        return match (true) {
            str_contains($key, 'mpesa'),
            str_contains($key, 'm_pesa'),
            str_contains($key, 'vodacom') => 'M-Pesa',
            str_contains($key, 'mixx'),
            str_contains($key, 'yas'),
            str_contains($key, 'tigo') => 'Mixx by Yas',
            str_contains($key, 'airtel') => 'Airtel Money',
            str_contains($key, 'halo') => 'Halopesa',
            default => filled($operator) ? ucwords(str_replace(['_', '-'], ' ', $operator)) : 'Mobile money',
        };
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

    /**
     * Admin list/detail context: product, application/loan, asset, partner.
     *
     * @return array{
     *   type: string,
     *   product: ?string,
     *   product_code: ?string,
     *   application_number: ?string,
     *   application_url: ?string,
     *   loan_number: ?string,
     *   loan_url: ?string,
     *   asset: ?string,
     *   partner: ?string,
     *   partner_role: ?string,
     *   lines: list<string>
     * }
     */
    public function adminContext(): array
    {
        $this->loadMissing(['loan', 'loanProduct', 'source']);

        $product = $this->loanProduct;
        $application = null;
        $assetTitle = null;
        $partnerName = null;
        $partnerRole = null;

        $source = $this->source;
        if ($source instanceof LoanApplication) {
            $application = $source;
            $application->loadMissing('product');
            $product = $product ?: $application->product;
        } elseif ($source instanceof AssetReservation) {
            $source->loadMissing(['asset.vendor', 'loanApplication.product']);
            $application = $source->loanApplication;
            $asset = $source->asset;
            $assetTitle = $asset?->title ?: $asset?->serial_number;
            $partnerName = $asset?->vendor?->name ?: ($asset?->supplier_name ?: null);
            $partnerRole = $partnerName ? 'Asset supplier / partner' : null;
            $product = $product ?: $application?->product;
        }

        $ctx = (array) data_get($this->provider_meta, 'apply_context', []);
        if (! $product && filled($ctx['loan_product_id'] ?? null)) {
            $product = LoanProduct::query()->find((int) $ctx['loan_product_id']);
        }
        if (! $application && filled($ctx['loan_application_id'] ?? null)) {
            $application = LoanApplication::query()->find((int) $ctx['loan_application_id']);
        }

        $applicationNumber = $application?->application_number;
        $applicationUrl = $application
            ? route('admin.loan-applications.show', $application)
            : null;
        $loanNumber = $this->loan?->loan_number;
        $loanUrl = $this->loan ? route('admin.loans.show', $this->loan) : null;

        // Partner from fee split / destinations meta when not from asset vendor.
        if (! $partnerName) {
            $feeSplit = (array) data_get($this->provider_meta, 'fee_split', []);
            $named = $feeSplit['partner_name'] ?? $feeSplit['gps_partner_name'] ?? null;
            if (filled($named)) {
                $partnerName = (string) $named;
                $partnerRole = 'Fee partner';
            }
        }

        $lines = array_values(array_filter([
            $this->typeLabel(),
            $product?->name ? ('Product · '.$product->name) : null,
            $applicationNumber ? ('Application · '.$applicationNumber) : null,
            $loanNumber ? ('Loan · '.$loanNumber) : null,
            $assetTitle ? ('Asset · '.$assetTitle) : null,
            $partnerName ? ('Partner · '.$partnerName) : null,
        ]));

        return [
            'type' => $this->typeLabel(),
            'product' => $product?->name,
            'product_code' => $product?->code,
            'application_number' => $applicationNumber,
            'application_url' => $applicationUrl,
            'loan_number' => $loanNumber,
            'loan_url' => $loanUrl,
            'asset' => $assetTitle,
            'partner' => $partnerName,
            'partner_role' => $partnerRole,
            'lines' => $lines,
        ];
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
            && (
                in_array($this->provider, ['payin', \App\Services\Staging\StagingPaymentsService::PROVIDER], true)
                || $this->payment_type === 'insurance_premium'
            );
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

    /** Best timestamp for admin lists: when money cleared, else when the row was created. */
    public function adminOccurredAt(): ?\Carbon\CarbonInterface
    {
        return $this->verified_at ?? $this->paid_at ?? $this->created_at;
    }

    /** Completed money in — PSP-approved mobile or admin-verified bank. */
    public function scopeComplete($query)
    {
        return $query->whereIn('status', ['verified', 'paid']);
    }

    /** Bank deposits waiting for staff match / clarification only. */
    public function scopeAwaitingBankVerification($query)
    {
        return $query
            ->where('payment_method', 'bank_transfer')
            ->whereIn('status', ['pending_verification', 'clarification_requested']);
    }
}
