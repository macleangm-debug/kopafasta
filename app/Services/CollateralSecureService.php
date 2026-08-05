<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerGuarantor;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Post-submit "secure this loan with collateral" ladder on loan profiles.
 * Product name + interest stay unchanged; AB application-fee schedule applies (anti-backdoor).
 */
class CollateralSecureService
{
    public const STATUS_AWAITING_BORROWER = 'awaiting_borrower_has_collateral';

    public const STATUS_AWAITING_ASK_GUARANTOR = 'awaiting_ask_guarantor';

    public const STATUS_AWAITING_BORROWER_ADD = 'awaiting_borrower_add';

    public const STATUS_AWAITING_GUARANTOR = 'awaiting_guarantor_consent';

    public const STATUS_AWAITING_FEE = 'awaiting_fee';

    public const STATUS_AWAITING_INSURANCE = 'awaiting_insurance';

    public const STATUS_SECURED = 'secured';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public function state(LoanApplication $application): ?array
    {
        $state = data_get($application->screening_payload, 'collateral_secure');

        return is_array($state) && ! empty($state['requested_at']) ? $state : null;
    }

    public function isOpen(LoanApplication $application): bool
    {
        $state = $this->state($application);
        if (! $state) {
            return false;
        }

        return ! in_array($state['status'] ?? '', [
            self::STATUS_SECURED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function decisionDays(): int
    {
        return max(1, (int) app(UnderwritingSettingsService::class)->get('collateral_secure_decision_days', 3));
    }

    public function insuranceBufferMonths(): int
    {
        return max(0, (int) app(UnderwritingSettingsService::class)->get('insurance_expiry_buffer_months', 2));
    }

    public function insuranceRenewalDays(): int
    {
        return max(1, (int) app(UnderwritingSettingsService::class)->get('insurance_renewal_decision_days', 5));
    }

    public function assetBackedFeeProduct(): ?LoanProduct
    {
        return LoanProduct::query()
            ->whereRaw('UPPER(code) = ?', ['AB'])
            ->where('is_active', true)
            ->first();
    }

    /** @return array{quoted: int, credit: int, due: int, prior_product_fee: int|null, new_product_fee: int}|null */
    public function feeQuote(LoanApplication $application): ?array
    {
        $ab = $this->assetBackedFeeProduct();
        if (! $ab || ! $application->customer) {
            return null;
        }

        return app(ApplicationFeeCreditService::class)->conversionQuote($application, $ab);
    }

    public function request(LoanApplication $application, User $admin, ?string $notes = null): array
    {
        if ($this->isOpen($application)) {
            throw new \InvalidArgumentException('A collateral request is already open on this application.');
        }

        if (is_asset_backed_loan_product($application->product?->code) || is_marketplace_loan_product($application->product?->code)) {
            throw new \InvalidArgumentException('This product already uses collateral / asset flow.');
        }

        $dueAt = now()->addDays($this->decisionDays());

        $state = [
            'requested_at' => now()->toIso8601String(),
            'requested_by' => $admin->id,
            'notes'        => $notes,
            'due_at'       => $dueAt->toIso8601String(),
            'status'       => self::STATUS_AWAITING_BORROWER,
            'borrower_has_collateral' => null,
            'ask_guarantor' => null,
            'source'       => null,
            'customer_asset_id' => null,
            'guarantor_customer_id' => null,
            'guarantor_asked_at' => null,
            'guarantor_due_at' => null,
            'guarantor_response' => null,
            'fee_due'      => null,
            'fee_paid_at'  => null,
            'secured_at'   => null,
            'preserve_product' => true,
        ];

        $this->saveState($application, $state);

        $customer = $application->customer;
        if ($customer instanceof Customer) {
            app(NotificationService::class)->notifyInApp(
                $customer,
                __('borrower.collateral_secure.notify_borrower_body', [
                    'reference' => $application->application_number ?? $application->id,
                ]),
                category: 'loan_application',
                template: 'collateral_secure_request',
                title: __('borrower.collateral_secure.notify_borrower_title'),
                actionUrl: route('site.borrower.application', $application),
                actionLabel: __('borrower.collateral_secure.cta_open'),
                i18n: [
                    'title_key' => 'borrower.collateral_secure.notify_borrower_title',
                    'body_key'  => 'borrower.collateral_secure.notify_borrower_body',
                    'params'    => ['reference' => $application->application_number ?? $application->id],
                ],
            );
        }

        return $state;
    }

    public function borrowerHasCollateral(LoanApplication $application, Customer $borrower, bool $has): array
    {
        $this->assertBorrower($application, $borrower);
        $state = $this->requireOpen($application);
        abort_unless(($state['status'] ?? '') === self::STATUS_AWAITING_BORROWER, 422);

        $state['borrower_has_collateral'] = $has;
        $state['borrower_answered_at'] = now()->toIso8601String();

        if ($has) {
            $state['status'] = self::STATUS_AWAITING_BORROWER_ADD;
            $state['source'] = 'borrower';
            $state['due_at'] = now()->addDays($this->decisionDays())->toIso8601String();
        } else {
            $state['status'] = self::STATUS_AWAITING_ASK_GUARANTOR;
        }

        $this->saveState($application, $state);

        return $state;
    }

    public function borrowerAskGuarantor(LoanApplication $application, Customer $borrower, bool $ask): array
    {
        $this->assertBorrower($application, $borrower);
        $state = $this->requireOpen($application);
        abort_unless(($state['status'] ?? '') === self::STATUS_AWAITING_ASK_GUARANTOR, 422);

        $state['ask_guarantor'] = $ask;
        $state['ask_guarantor_answered_at'] = now()->toIso8601String();

        if (! $ask) {
            return $this->rejectForNoCollateral($application, $state, 'Borrower declined to ask guarantor for collateral.');
        }

        $guarantorCustomer = $this->resolveGuarantorCustomer($application);
        if (! $guarantorCustomer) {
            return $this->rejectForNoCollateral($application, $state, 'No guarantor on file to ask for collateral.');
        }

        $dueAt = now()->addDays($this->decisionDays());
        $state['status'] = self::STATUS_AWAITING_GUARANTOR;
        $state['source'] = 'guarantor';
        $state['guarantor_customer_id'] = $guarantorCustomer->id;
        $state['guarantor_asked_at'] = now()->toIso8601String();
        $state['guarantor_due_at'] = $dueAt->toIso8601String();
        $state['due_at'] = $dueAt->toIso8601String();
        $this->saveState($application, $state);

        $linkId = $this->guarantorLinkId($application, $guarantorCustomer);
        app(NotificationService::class)->notifyInApp(
            $guarantorCustomer,
            __('borrower.collateral_secure.notify_guarantor_body', [
                'borrower' => $borrower->legalDisplayName() ?? $borrower->full_name,
                'reference' => $application->application_number ?? $application->id,
            ]),
            category: 'loan_application',
            template: 'collateral_secure_guarantor_ask',
            title: __('borrower.collateral_secure.notify_guarantor_title'),
            actionUrl: $linkId
                ? route('site.borrower.guaranteed.show', $linkId)
                : route('site.borrower.loans', ['tab' => 'guarantor']),
            actionLabel: __('borrower.collateral_secure.cta_respond'),
            i18n: [
                'title_key' => 'borrower.collateral_secure.notify_guarantor_title',
                'body_key'  => 'borrower.collateral_secure.notify_guarantor_body',
                'params'    => [
                    'borrower' => $borrower->legalDisplayName() ?? $borrower->full_name,
                    'reference' => $application->application_number ?? $application->id,
                ],
            ],
        );

        return $state;
    }

    public function guarantorRespond(LoanApplication $application, Customer $guarantor, bool $accept): array
    {
        $state = $this->requireOpen($application);
        abort_unless(($state['status'] ?? '') === self::STATUS_AWAITING_GUARANTOR, 422);
        abort_unless((int) ($state['guarantor_customer_id'] ?? 0) === (int) $guarantor->id, 403);

        $state['guarantor_response'] = $accept ? 'accepted' : 'declined';
        $state['guarantor_responded_at'] = now()->toIso8601String();

        if (! $accept) {
            return $this->rejectForNoCollateral($application, $state, 'Guarantor declined to provide collateral.');
        }

        $state['status'] = self::STATUS_AWAITING_BORROWER_ADD;
        $state['due_at'] = now()->addDays($this->decisionDays())->toIso8601String();
        $this->saveState($application, $state);

        if ($application->customer) {
            app(NotificationService::class)->notifyInApp(
                $application->customer,
                __('borrower.collateral_secure.notify_guarantor_accepted_body'),
                category: 'loan_application',
                template: 'collateral_secure_guarantor_accepted',
                title: __('borrower.collateral_secure.notify_guarantor_accepted_title'),
                actionUrl: route('site.borrower.application', $application),
                actionLabel: __('borrower.collateral_secure.cta_open'),
            );
        }

        return $state;
    }

    public function linkAsset(LoanApplication $application, Customer $actor, CustomerAsset $asset): array
    {
        $state = $this->requireOpen($application);
        abort_unless(in_array($state['status'] ?? '', [
            self::STATUS_AWAITING_BORROWER_ADD,
            self::STATUS_AWAITING_INSURANCE,
        ], true), 422);

        $source = $state['source'] ?? 'borrower';
        if ($source === 'guarantor') {
            abort_unless((int) $asset->customer_id === (int) ($state['guarantor_customer_id'] ?? 0), 403);
            abort_unless((int) $actor->id === (int) ($state['guarantor_customer_id'] ?? 0), 403);
        } else {
            abort_unless((int) $asset->customer_id === (int) $application->customer_id, 403);
            abort_unless((int) $actor->id === (int) $application->customer_id, 403);
        }

        $insurance = $this->insuranceCheck($application, $asset);
        $state['customer_asset_id'] = $asset->id;
        $state['insurance'] = $insurance;

        if (! ($insurance['ok'] ?? false)) {
            $state['status'] = self::STATUS_AWAITING_INSURANCE;
            $state['insurance_due_at'] = now()->addDays($this->insuranceRenewalDays())->toIso8601String();
            $this->saveState($application, $state);

            return $state;
        }

        return $this->advanceAfterAsset($application, $state, $asset);
    }

    public function markFeePaid(LoanApplication $application): array
    {
        $state = $this->requireOpen($application);
        abort_unless(($state['status'] ?? '') === self::STATUS_AWAITING_FEE, 422);

        $quote = $this->feeQuote($application);
        $ab = $this->assetBackedFeeProduct();
        $asset = CustomerAsset::query()->find($state['customer_asset_id'] ?? 0);
        abort_unless($asset && $ab, 422);

        $state['fee_paid_at'] = now()->toIso8601String();
        $state['fee_due'] = 0;

        return $this->finalizeSecured($application, $state, $asset, $ab, $quote);
    }

    public function expireIfNeeded(LoanApplication $application): ?array
    {
        $state = $this->state($application);
        if (! $state || ! $this->isOpen($application)) {
            return $state;
        }

        $due = $state['due_at'] ?? $state['guarantor_due_at'] ?? $state['insurance_due_at'] ?? null;
        if (! $due || now()->lt(\Carbon\Carbon::parse($due))) {
            return $state;
        }

        return $this->rejectForNoCollateral($application, $state, 'Collateral decision window expired.');
    }

    /**
     * @return array{ok: bool, expiry: ?string, required_by: ?string, reason: ?string, renewal_days: int}
     */
    public function insuranceCheck(LoanApplication $application, CustomerAsset $asset): array
    {
        $renewalDays = $this->insuranceRenewalDays();
        if ($asset->asset_type !== 'vehicle') {
            return [
                'ok' => true,
                'expiry' => null,
                'required_by' => null,
                'reason' => null,
                'renewal_days' => $renewalDays,
            ];
        }

        $expiryRaw = $asset->detail('insurance_expires_at');
        if (! filled($expiryRaw)) {
            return [
                'ok' => false,
                'expiry' => null,
                'required_by' => null,
                'reason' => 'missing',
                'renewal_days' => $renewalDays,
            ];
        }

        try {
            $expiry = \Carbon\Carbon::parse((string) $expiryRaw)->startOfDay();
        } catch (\Throwable) {
            return [
                'ok' => false,
                'expiry' => null,
                'required_by' => null,
                'reason' => 'invalid',
                'renewal_days' => $renewalDays,
            ];
        }

        $tenure = max(1, (int) ($application->requested_tenure_months ?? 1));
        $requiredBy = now()->startOfDay()
            ->addMonthsNoOverflow($tenure + $this->insuranceBufferMonths());

        if ($expiry->lt(now()->startOfDay()->addMonth())) {
            return [
                'ok' => false,
                'expiry' => $expiry->toDateString(),
                'required_by' => $requiredBy->toDateString(),
                'reason' => 'expiring_soon',
                'renewal_days' => $renewalDays,
            ];
        }

        if ($expiry->lt($requiredBy)) {
            return [
                'ok' => false,
                'expiry' => $expiry->toDateString(),
                'required_by' => $requiredBy->toDateString(),
                'reason' => 'buffer',
                'renewal_days' => $renewalDays,
            ];
        }

        return [
            'ok' => true,
            'expiry' => $expiry->toDateString(),
            'required_by' => $requiredBy->toDateString(),
            'reason' => null,
            'renewal_days' => $renewalDays,
        ];
    }

    /** @return array<string, mixed> */
    public function viewModel(LoanApplication $application): array
    {
        $this->expireIfNeeded($application->fresh());
        $application->refresh();
        $state = $this->state($application);
        if (! $state) {
            return ['active' => false];
        }

        $daysLeft = null;
        if (! empty($state['due_at'])) {
            $due = \Carbon\Carbon::parse($state['due_at']);
            $daysLeft = $due->isPast() ? 0 : (int) now()->startOfDay()->diffInDays($due->copy()->startOfDay());
        }

        return [
            'active' => $this->isOpen($application) || ($state['status'] ?? '') === self::STATUS_SECURED,
            'open' => $this->isOpen($application),
            'state' => $state,
            'status' => $state['status'] ?? null,
            'days_left' => $daysLeft,
            'fee_quote' => $this->feeQuote($application),
            'add_collateral_url' => route('site.borrower.profile', ['section' => 'assets', 'add' => 1]),
            'assets' => $this->selectableAssets($application, $state),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, CustomerAsset> */
    private function selectableAssets(LoanApplication $application, array $state)
    {
        $ownerId = ($state['source'] ?? 'borrower') === 'guarantor'
            ? (int) ($state['guarantor_customer_id'] ?? 0)
            : (int) $application->customer_id;

        if ($ownerId <= 0) {
            return collect();
        }

        return app(CustomerAssetService::class)->forCustomer(
            Customer::query()->findOrFail($ownerId)
        );
    }

    private function advanceAfterAsset(LoanApplication $application, array $state, CustomerAsset $asset): array
    {
        $ab = $this->assetBackedFeeProduct();
        $quote = $this->feeQuote($application);
        $due = (int) ($quote['due'] ?? 0);
        $state['fee_due'] = $due;

        if ($due > 0) {
            $state['status'] = self::STATUS_AWAITING_FEE;
            $this->saveState($application, $state);

            if ($application->customer) {
                app(NotificationService::class)->notifyInApp(
                    $application->customer,
                    __('borrower.collateral_secure.notify_fee_body', [
                        'amount' => format_money($due),
                    ]),
                    category: 'loan_application',
                    template: 'collateral_secure_fee',
                    title: __('borrower.collateral_secure.notify_fee_title'),
                    actionUrl: route('site.borrower.application', $application),
                    actionLabel: __('borrower.collateral_secure.cta_pay'),
                );
            }

            return $state;
        }

        abort_unless($ab, 422, 'Asset-backed fee product (AB) is not configured.');

        return $this->finalizeSecured($application, $state, $asset, $ab, $quote ?? [
            'quoted' => 0,
            'credit' => 0,
            'due' => 0,
        ]);
    }

    /** @param array{quoted?: int, credit?: int, due?: int} $quote */
    private function finalizeSecured(
        LoanApplication $application,
        array $state,
        CustomerAsset $asset,
        LoanProduct $ab,
        array $quote,
    ): array {
        return DB::transaction(function () use ($application, $state, $asset, $ab, $quote) {
            $gpsRequired = in_array($asset->asset_type, ['vehicle', 'equipment'], true);

            LoanApplicationAsset::query()->updateOrCreate(
                [
                    'loan_application_id' => $application->id,
                    'customer_asset_id'   => $asset->id,
                ],
                [
                    'asset_type'       => $asset->asset_type,
                    'description'      => $asset->label,
                    'gps_required'     => $gpsRequired,
                    'valuation_status' => 'awaiting_valuation',
                    'uw_status'        => LoanApplicationAsset::UW_PENDING,
                    'is_primary'       => true,
                ]
            );

            $newFee = (int) ($quote['quoted'] ?? $quote['new_product_fee'] ?? 0);
            $application->update([
                'application_fee_amount' => max((int) ($application->application_fee_amount ?? 0), $newFee),
                'application_fee_status' => (($quote['due'] ?? 0) <= 0 && ($state['fee_paid_at'] ?? null))
                    || (($quote['due'] ?? 0) <= 0)
                    ? 'paid'
                    : ($application->application_fee_status ?? 'unpaid'),
            ]);

            // Keep product name + interest; only flag secured path for ops.
            $state['status'] = self::STATUS_SECURED;
            $state['secured_at'] = now()->toIso8601String();
            $state['fee_schedule_product_id'] = $ab->id;
            $this->saveState($application, $state);

            return $state;
        });
    }

    private function rejectForNoCollateral(LoanApplication $application, array $state, string $reason): array
    {
        $state['status'] = self::STATUS_REJECTED;
        $state['rejected_at'] = now()->toIso8601String();
        $state['reject_reason'] = $reason;
        $this->saveState($application, $state);

        $application->update([
            'status' => 'rejected',
            'current_stage' => 'rejected',
            'rejection_reason_code' => $application->rejection_reason_code ?: 'collateral_not_provided',
            'rejection_advice' => __('borrower.collateral_secure.rejected_advice'),
        ]);

        if ($application->customer) {
            app(NotificationService::class)->notifyInApp(
                $application->customer,
                __('borrower.collateral_secure.notify_rejected_body'),
                category: 'loan_application',
                template: 'collateral_secure_rejected',
                title: __('borrower.collateral_secure.notify_rejected_title'),
                actionUrl: route('site.borrower.application', $application),
                actionLabel: __('borrower.collateral_secure.cta_open'),
            );
        }

        return $state;
    }

    private function saveState(LoanApplication $application, array $state): void
    {
        $payload = $application->screening_payload ?? [];
        $payload['collateral_secure'] = $state;
        $application->update(['screening_payload' => $payload]);
    }

    private function requireOpen(LoanApplication $application): array
    {
        $this->expireIfNeeded($application);
        $application->refresh();
        $state = $this->state($application);
        abort_unless($state && $this->isOpen($application), 422, 'No open collateral request.');

        return $state;
    }

    private function assertBorrower(LoanApplication $application, Customer $borrower): void
    {
        abort_unless((int) $application->customer_id === (int) $borrower->id, 403);
    }

    private function resolveGuarantorCustomer(LoanApplication $application): ?Customer
    {
        $link = CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->whereNotIn('status', ['rejected'])
            ->latest('id')
            ->first();

        if ($link) {
            $customer = app(GuarantorAccessService::class)->guarantorCustomerForLink($link);
            if ($customer) {
                return $customer;
            }
        }

        $invite = \App\Models\GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->whereNotNull('guarantor_customer_id')
            ->latest('id')
            ->first();

        return $invite?->guarantorCustomer;
    }

    private function guarantorLinkId(LoanApplication $application, Customer $guarantor): ?int
    {
        $link = CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->whereNotIn('status', ['rejected'])
            ->latest('id')
            ->get()
            ->first(function (CustomerGuarantor $link) use ($guarantor) {
                $customer = app(GuarantorAccessService::class)->guarantorCustomerForLink($link);

                return $customer && (int) $customer->id === (int) $guarantor->id;
            });

        return $link?->id;
    }
}
