<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerGuarantor;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Post-submit "secure this loan with collateral" ladder on loan profiles.
 * Product name + interest stay unchanged; AB application-fee schedule applies (anti-backdoor).
 */
class CollateralSecureService
{
    public const STATUS_AWAITING_BORROWER = 'awaiting_borrower_has_collateral';

    public const STATUS_AWAITING_ASK_GUARANTOR = 'awaiting_ask_guarantor';

    public const STATUS_AWAITING_ASK_MEMBERS = 'awaiting_ask_members';

    public const STATUS_AWAITING_MEMBERS = 'awaiting_members';

    public const STATUS_AWAITING_BORROWER_ADD = 'awaiting_borrower_add';

    public const STATUS_AWAITING_GUARANTOR = 'awaiting_guarantor_consent';

    public const STATUS_AWAITING_FEE = 'awaiting_fee';

    /** @deprecated Insurance is post-offer; kept for in-flight applications. */
    public const STATUS_AWAITING_INSURANCE = 'awaiting_insurance';

    public const STATUS_AWAITING_VALUATION_FEE = 'awaiting_valuation_fee';

    public const STATUS_AWAITING_VALUER = 'awaiting_valuer';

    public const STATUS_SHORTFALL = 'collateral_shortfall';

    public const STATUS_SECURED = 'secured';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    /** Existing ladder: borrower/guarantor ask + AB fee delta + valuation. */
    public const PATH_SECURE = 'collateral_secure';

    /** Screening reviewed pledged collateral: valuation fee only (product stays IL/GL). */
    public const PATH_SCREENING_VALUATION = 'screening_valuation';

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
            self::STATUS_AWAITING_VALUER,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
        ], true);
    }

    /** True when borrower still owes VAL_FEE (settings hub ChargesFee) before valuer assignment. */
    public function needsValuationFeePayment(LoanApplication $application): bool
    {
        $this->healValuationFeeGate($application);
        $application->refresh();
        $state = $this->state($application);
        if (! $state) {
            return false;
        }

        if (($state['status'] ?? '') === self::STATUS_AWAITING_VALUATION_FEE) {
            return true;
        }

        if (($state['status'] ?? '') !== self::STATUS_SECURED) {
            return false;
        }

        if (filled($state['valuation_fee_paid_at'] ?? null)) {
            return false;
        }

        $assetRow = LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->where('customer_asset_id', (int) ($state['customer_asset_id'] ?? 0))
            ->first();

        if (filled($assetRow?->valuation_fee_paid_at)) {
            return false;
        }

        return (int) quoted_valuation_fee($application->customer) > 0;
    }

    /**
     * Apps that reached "secured" without VAL_FEE (legacy / zero-then-fee) reopen the pay gate.
     */
    public function healValuationFeeGate(LoanApplication $application): void
    {
        $state = $this->state($application);
        if (! $state || ($state['status'] ?? '') !== self::STATUS_SECURED) {
            return;
        }

        if (filled($state['valuation_fee_paid_at'] ?? null)) {
            return;
        }

        $due = (int) quoted_valuation_fee($application->customer);
        if ($due <= 0) {
            return;
        }

        $assetRow = LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->where('customer_asset_id', (int) ($state['customer_asset_id'] ?? 0))
            ->first();

        if (filled($assetRow?->valuation_fee_paid_at)) {
            $state['valuation_fee_paid_at'] = optional($assetRow->valuation_fee_paid_at)?->toIso8601String();
            $this->saveState($application, $state);

            return;
        }

        $state['status'] = self::STATUS_AWAITING_VALUATION_FEE;
        $state['valuation_fee_due'] = $due;
        unset($state['secured_at']);
        $this->saveState($application, $state);
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

    /** Extra days after the decision window before the application is closed. */
    public function graceDays(): int
    {
        return max(0, (int) app(UnderwritingSettingsService::class)->get('collateral_secure_grace_days', 3));
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
            'path'         => self::PATH_SECURE,
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

    /**
     * Screening has reviewed pledged collateral. Collect valuation fee from the
     * application owner (group leader / individual borrower). Do not assign a valuer yet.
     */
    public function requestValuation(LoanApplication $application, User $admin, ?string $notes = null): array
    {
        if (is_asset_backed_loan_product($application->product?->code) || is_marketplace_loan_product($application->product?->code)) {
            throw new \InvalidArgumentException('Asset-backed applications assign a valuer after the apply-flow valuation fee, not from this CTA.');
        }

        $existing = $this->state($application);
        if (($existing['status'] ?? '') === self::STATUS_AWAITING_VALUATION_FEE) {
            return $existing;
        }
        if (in_array($existing['status'] ?? '', [self::STATUS_AWAITING_VALUER, self::STATUS_AWAITING_INSURANCE, self::STATUS_SHORTFALL], true)) {
            return $existing;
        }
        if ($this->isOpen($application)) {
            throw new \InvalidArgumentException('Finish the open collateral request first, or wait for the valuation fee to be paid.');
        }

        $application->loadMissing('collateralAssets.customerAsset');
        $pledge = $application->collateralAssets
            ->first(fn (LoanApplicationAsset $row) => ($row->uw_status ?? '') !== LoanApplicationAsset::UW_DECLINED && $row->customerAsset);

        if (! $pledge?->customerAsset) {
            throw new \InvalidArgumentException('Pledge an asset on this loan before sending to the valuer.');
        }

        $asset = $pledge->customerAsset;
        $isGuarantor = (int) $asset->customer_id !== (int) $application->customer_id;
        $due = (int) quoted_valuation_fee($application->customer);
        $dueAt = now()->addDays($this->decisionDays());

        $state = [
            'requested_at' => now()->toIso8601String(),
            'requested_by' => $admin->id,
            'notes'        => $notes,
            'due_at'       => $dueAt->toIso8601String(),
            'path'         => self::PATH_SCREENING_VALUATION,
            'status'       => $due > 0 ? self::STATUS_AWAITING_VALUATION_FEE : self::STATUS_AWAITING_VALUER,
            'source'       => $isGuarantor ? 'guarantor' : 'borrower',
            'customer_asset_id' => $asset->id,
            'guarantor_customer_id' => $isGuarantor ? $asset->customer_id : null,
            'valuation_fee_due' => $due,
            'valuation_fee_paid_at' => $due > 0 ? null : now()->toIso8601String(),
            'preserve_product' => true,
        ];

        $this->saveState($application, $state);

        if ($due <= 0) {
            app(ValuationPartnerService::class)->autoAssignIfPossible($application->fresh(), $admin);

            return $state;
        }

        $payer = $application->customer;
        if ($payer instanceof Customer) {
            app(NotificationService::class)->notifyInApp(
                $payer,
                __('borrower.collateral_secure.notify_valuation_fee_body', [
                    'amount' => format_money($due),
                ]),
                category: 'loan_application',
                template: 'collateral_secure_valuation_fee',
                title: __('borrower.collateral_secure.notify_valuation_fee_title'),
                actionUrl: route('site.borrower.application', $application),
                actionLabel: __('borrower.collateral_secure.cta_pay_valuation'),
            );
        }

        return $state;
    }

    public function applyCoverageOutcome(LoanApplication $application, array $coverage): void
    {
        $state = $this->state($application);
        if (! $state || ($state['path'] ?? self::PATH_SECURE) !== self::PATH_SCREENING_VALUATION) {
            return;
        }

        $next = (string) ($coverage['next'] ?? CollateralCoverageService::NEXT_OK);
        if ($next === CollateralCoverageService::NEXT_INSURANCE) {
            $state['status'] = self::STATUS_AWAITING_INSURANCE;
            $state['insurance'] = $coverage['insurance'] ?? null;
            $state['insurance_due_at'] = now()->addDays($this->insuranceRenewalDays())->toIso8601String();
        } elseif (! ($coverage['sufficient'] ?? false)) {
            $state['status'] = self::STATUS_SHORTFALL;
            $state['due_at'] = now()->addDays($this->decisionDays())->toIso8601String();
        } else {
            $state['status'] = self::STATUS_SECURED;
            $state['secured_at'] = now()->toIso8601String();
        }

        $this->saveState($application, $state);
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
        } elseif ($this->isGroupFile($application)) {
            $state['status'] = self::STATUS_AWAITING_ASK_MEMBERS;
        } else {
            $state['status'] = self::STATUS_AWAITING_ASK_GUARANTOR;
        }

        $this->saveState($application, $state);

        return $state;
    }

    public function borrowerAskMembers(LoanApplication $application, Customer $borrower, bool $ask): array
    {
        $this->assertBorrower($application, $borrower);
        $state = $this->requireOpen($application);
        abort_unless(($state['status'] ?? '') === self::STATUS_AWAITING_ASK_MEMBERS, 422);

        $state['ask_members'] = $ask;
        $state['ask_members_answered_at'] = now()->toIso8601String();

        if (! $ask) {
            return $this->rejectForNoCollateral($application, $state, 'Group leader declined to ask members for collateral.');
        }

        $members = $this->activeNonLeaderMembers($application);
        if ($members->isEmpty()) {
            return $this->rejectForNoCollateral($application, $state, 'No group members to ask for collateral.');
        }

        $dueAt = now()->addDays($this->decisionDays());
        $state['status'] = self::STATUS_AWAITING_MEMBERS;
        $state['source'] = 'member';
        $state['members_asked_at'] = now()->toIso8601String();
        $state['due_at'] = $dueAt->toIso8601String();
        $this->saveState($application, $state);

        $docs = app(ApplicationDocumentRequestService::class);
        $admin = User::query()->find((int) ($state['requested_by'] ?? 0));
        if ($admin) {
            try {
                $docs->createManyForActiveGroupMembers(
                    $application,
                    $admin,
                    [ApplicationDocumentRequestService::COLLATERAL_PRESET_LABELS[0]],
                    null,
                    $dueAt,
                );
            } catch (\InvalidArgumentException) {
                // Members were already notified below if create fails on empty set.
            }
        }

        foreach ($members as $member) {
            $customer = $member->customer;
            if (! $customer) {
                continue;
            }
            app(NotificationService::class)->notifyInApp(
                $customer,
                __('borrower.collateral_secure.notify_member_body', [
                    'leader' => $borrower->legalDisplayName() ?? $borrower->full_name,
                    'reference' => $application->application_number ?? $application->id,
                ]),
                category: 'loan_application',
                template: 'collateral_secure_member_ask',
                title: __('borrower.collateral_secure.notify_member_title'),
                actionUrl: route('site.borrower.application', $application),
                actionLabel: __('borrower.collateral_secure.cta_respond'),
                i18n: [
                    'title_key' => 'borrower.collateral_secure.notify_member_title',
                    'body_key'  => 'borrower.collateral_secure.notify_member_body',
                    'params'    => [
                        'leader' => $borrower->legalDisplayName() ?? $borrower->full_name,
                        'reference' => $application->application_number ?? $application->id,
                    ],
                ],
            );
        }

        return $state;
    }

    public function maybeAcceptMemberPledge(LoanApplication $application, Customer $member, CustomerAsset $asset): array
    {
        $state = $this->state($application);
        if (! is_array($state) || ($state['status'] ?? '') !== self::STATUS_AWAITING_MEMBERS) {
            return $state ?? [];
        }

        $members = $this->activeNonLeaderMembers($application);
        $isMember = $members->contains(fn ($row) => (int) $row->customer_id === (int) $member->id);
        if (! $isMember || (int) $asset->customer_id !== (int) $member->id) {
            return $state;
        }

        $state['source'] = 'member';
        $state['member_customer_id'] = $member->id;
        $state['customer_asset_id'] = $asset->id;

        return $this->advanceAfterAsset($application, $state, $asset);
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

        $state['customer_asset_id'] = $asset->id;
        $state['insurance'] = $this->insuranceCheck($application, $asset);

        // Fee delta is due as soon as collateral is accepted — insurance comes after payment.
        if (($state['status'] ?? '') === self::STATUS_AWAITING_INSURANCE || filled($state['fee_paid_at'] ?? null)) {
            return $this->advanceAfterFee($application, $state, $asset);
        }

        return $this->advanceAfterAsset($application, $state, $asset);
    }

    public function markFeePaid(LoanApplication $application): array
    {
        $state = $this->requireOpen($application);
        abort_unless(($state['status'] ?? '') === self::STATUS_AWAITING_FEE, 422);

        $asset = CustomerAsset::query()->find($state['customer_asset_id'] ?? 0);
        abort_unless($asset && $this->assetBackedFeeProduct(), 422);

        $state['fee_paid_at'] = now()->toIso8601String();
        $state['fee_due'] = 0;

        return $this->advanceAfterFee($application, $state, $asset);
    }

    /**
     * After insurance is updated on the pledged asset (owner profile or partner case), continue.
     *
     * @return array<string, mixed>|null
     */
    public function recheckAfterInsuranceUpdate(LoanApplication $application, CustomerAsset $asset): ?array
    {
        $state = $this->state($application);
        if (! $state || ($state['status'] ?? '') !== self::STATUS_AWAITING_INSURANCE) {
            return $state;
        }

        if ((int) ($state['customer_asset_id'] ?? 0) !== (int) $asset->id) {
            return $state;
        }

        if (($state['path'] ?? self::PATH_SECURE) === self::PATH_SCREENING_VALUATION) {
            app(CollateralCoverageService::class)->evaluate($application);

            return $this->state($application->fresh());
        }

        return $this->advanceAfterFee($application, $state, $asset);
    }

    /**
     * Persist insurance purchase / partner case onto collateral_secure state.
     *
     * @param  array<string, mixed>  $purchase
     * @return array<string, mixed>
     */
    public function recordInsurancePurchase(LoanApplication $application, array $purchase): array
    {
        $state = $this->requireOpen($application);
        abort_unless(($state['status'] ?? '') === self::STATUS_AWAITING_INSURANCE, 422);

        $state['insurance_purchase'] = $purchase;
        $this->saveState($application, $state);

        return $state;
    }

    public function expireIfNeeded(LoanApplication $application): ?array
    {
        $state = $this->state($application);
        if (! $state || ! $this->isOpen($application)) {
            return $state;
        }

        $hardExpireAt = $this->hardExpireAt($state);
        if (! $hardExpireAt || now()->lt($hardExpireAt)) {
            return $state;
        }

        return $this->rejectForNoCollateral($application, $state, 'Collateral decision window expired.');
    }

    /**
     * Active deadline for the current step (insurance uses insurance_due_at).
     */
    public function effectiveDueAt(array $state): ?\Carbon\Carbon
    {
        $status = $state['status'] ?? '';

        if ($status === self::STATUS_AWAITING_INSURANCE && ! empty($state['insurance_due_at'])) {
            return \Carbon\Carbon::parse($state['insurance_due_at']);
        }

        if ($status === self::STATUS_AWAITING_GUARANTOR && ! empty($state['guarantor_due_at'])) {
            return \Carbon\Carbon::parse($state['guarantor_due_at']);
        }

        if (! empty($state['due_at'])) {
            return \Carbon\Carbon::parse($state['due_at']);
        }

        if (! empty($state['guarantor_due_at'])) {
            return \Carbon\Carbon::parse($state['guarantor_due_at']);
        }

        if (! empty($state['insurance_due_at'])) {
            return \Carbon\Carbon::parse($state['insurance_due_at']);
        }

        return null;
    }

    /** Decision due date plus grace — after this the loan is closed. */
    public function hardExpireAt(array $state): ?\Carbon\Carbon
    {
        $due = $this->effectiveDueAt($state);

        return $due?->copy()->addDays($this->graceDays());
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
                'insurance_type' => $asset->insuranceType(),
            ];
        }

        if ($expiry->lt($requiredBy)) {
            return [
                'ok' => false,
                'expiry' => $expiry->toDateString(),
                'required_by' => $requiredBy->toDateString(),
                'reason' => 'buffer',
                'renewal_days' => $renewalDays,
                'insurance_type' => $asset->insuranceType(),
            ];
        }

        return [
            'ok' => true,
            'expiry' => $expiry->toDateString(),
            'required_by' => $requiredBy->toDateString(),
            'reason' => null,
            'renewal_days' => $renewalDays,
            'insurance_type' => $asset->insuranceType(),
        ];
    }

    /** @return array{due: int, currency: string} */
    public function valuationFeeQuote(LoanApplication $application): array
    {
        $due = (int) quoted_valuation_fee($application->customer);

        return [
            'due' => $due,
            'currency' => MembershipService::config()['currency'] ?? 'TZS',
        ];
    }

    /**
     * Borrower-facing valuation ladder after collateral is linked.
     *
     * @param  array<string, mixed>  $state
     * @return array{status: string, label: string, pay_url: ?string, amount: int}
     */
    public function valuationProgress(LoanApplication $application, array $state): array
    {
        $status = (string) ($state['status'] ?? '');
        $amount = (int) ($state['valuation_fee_due'] ?? quoted_valuation_fee($application->customer));
        $payUrl = route('site.borrower.collateral-secure.pay-valuation', $application);

        if ($status === self::STATUS_AWAITING_VALUATION_FEE) {
            return [
                'status' => 'pay_valuation',
                'label' => __('borrower.collateral_secure.valuation_status_pay'),
                'pay_url' => $payUrl,
                'amount' => max($amount, (int) quoted_valuation_fee($application->customer)),
            ];
        }

        $assetRow = LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->where('customer_asset_id', (int) ($state['customer_asset_id'] ?? 0))
            ->first();

        $vs = (string) ($assetRow?->valuation_status ?? '');
        if ($vs === 'assigned' || $vs === 'in_progress') {
            return [
                'status' => 'in_progress',
                'label' => __('borrower.collateral_secure.valuation_status_in_progress'),
                'pay_url' => null,
                'amount' => 0,
            ];
        }
        if ($vs === 'completed') {
            return [
                'status' => 'completed',
                'label' => __('borrower.collateral_secure.valuation_status_completed'),
                'pay_url' => null,
                'amount' => 0,
            ];
        }

        if ($status === self::STATUS_SECURED || $assetRow) {
            return [
                'status' => 'awaiting_valuer',
                'label' => __('borrower.collateral_secure.valuation_status_awaiting_valuer'),
                'pay_url' => null,
                'amount' => 0,
            ];
        }

        return [
            'status' => 'idle',
            'label' => __('borrower.collateral_secure.valuation_status_pay'),
            'pay_url' => $payUrl,
            'amount' => $amount,
        ];
    }

    /** @return array<string, mixed> */
    public function viewModel(LoanApplication $application): array
    {
        $this->expireIfNeeded($application->fresh());
        $this->healValuationFeeGate($application->fresh());
        $application->refresh();
        $state = $this->state($application);
        if (! $state) {
            return ['active' => false];
        }

        $coverage = app(CollateralCoverageService::class)->forApplication($application);

        $daysLeft = null;
        $inGrace = false;
        $due = $this->effectiveDueAt($state);
        $hardExpire = $this->hardExpireAt($state);
        if ($hardExpire) {
            $daysLeft = $hardExpire->isPast()
                ? 0
                : (int) now()->startOfDay()->diffInDays($hardExpire->copy()->startOfDay());
            $inGrace = $due && $due->isPast() && ! $hardExpire->isPast();
        }

        $selected = null;
        if (! empty($state['customer_asset_id'])) {
            $asset = CustomerAsset::query()->find($state['customer_asset_id']);
            if ($asset) {
                $selected = $this->assetCard($asset);
            }
        }

        $ownerId = match ($state['source'] ?? 'borrower') {
            'guarantor' => (int) ($state['guarantor_customer_id'] ?? 0),
            'member' => (int) ($state['member_customer_id'] ?? 0),
            default => (int) $application->customer_id,
        };

        return [
            'active' => ! in_array($state['status'] ?? '', [self::STATUS_REJECTED, self::STATUS_EXPIRED], true),
            'open' => $this->isOpen($application),
            'state' => $state,
            'status' => $state['status'] ?? null,
            'path' => $state['path'] ?? self::PATH_SECURE,
            'days_left' => $daysLeft,
            'in_grace' => $inGrace,
            'grace_days' => $this->graceDays(),
            'fee_quote' => ($state['path'] ?? self::PATH_SECURE) === self::PATH_SCREENING_VALUATION
                ? null
                : $this->feeQuote($application),
            'valuation_fee_quote' => $this->valuationFeeQuote($application),
            'valuation_progress' => $this->valuationProgress($application, $state),
            'coverage' => $coverage,
            'add_collateral_url' => route('site.borrower.profile', ['section' => 'assets', 'add' => 1]),
            'owner_assets_url' => route('site.borrower.profile', [
                'section' => 'assets',
                'edit' => $state['customer_asset_id'] ?? null,
            ]),
            'assets' => $this->selectableAssets($application, $state)->map(fn (CustomerAsset $a) => $this->assetCard($a))->values(),
            'selected_asset' => $selected,
            'insurance' => $state['insurance'] ?? null,
            'insurance_purchase' => $state['insurance_purchase'] ?? null,
            'insurance_quote_defaults' => [
                'rate_percent' => app(CollateralInsurancePartnerService::class)->ratePercent(),
                'markup_percent' => app(CollateralInsurancePartnerService::class)->markupPercent(),
                'suggested_value' => $selected
                    ? (int) round((float) (CustomerAsset::query()->find($selected['id'] ?? 0)?->estimated_value ?? 0))
                    : 0,
            ],
            'is_guarantor_source' => ($state['source'] ?? '') === 'guarantor',
            'is_member_source' => ($state['source'] ?? '') === 'member',
            'owner_customer_id' => $ownerId,
        ];
    }

    /** @return array<string, mixed> */
    private function assetCard(CustomerAsset $asset): array
    {
        $typeOptions = CustomerAsset::typeOptions();

        return [
            'id' => $asset->id,
            'label' => $asset->label,
            'asset_type' => $asset->asset_type,
            'type_label' => $typeOptions[$asset->asset_type] ?? $asset->asset_type,
            'registration_number' => $asset->registration_number,
            'thumbnail' => $asset->thumbnailPath() ? asset('storage/'.$asset->thumbnailPath()) : null,
            'insurance_type' => $asset->insuranceType(),
            'insurance_expires_at' => $asset->detail('insurance_expires_at'),
            'insurance_policy_number' => $asset->detail('insurance_policy_number'),
            'has_insurance_doc' => $asset->hasVehicleInsurance(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, CustomerAsset> */
    private function selectableAssets(LoanApplication $application, array $state)
    {
        $ownerId = match ($state['source'] ?? 'borrower') {
            'guarantor' => (int) ($state['guarantor_customer_id'] ?? 0),
            'member' => (int) ($state['member_customer_id'] ?? 0),
            default => (int) $application->customer_id,
        };

        if ($ownerId <= 0) {
            return collect();
        }

        return app(CustomerAssetService::class)->forCustomer(
            Customer::query()->findOrFail($ownerId)
        )->reject(function (CustomerAsset $asset) use ($application) {
            return app(CustomerAssetService::class)->isPledgedToAnotherApplication(
                $asset,
                (int) $application->id
            );
        })->values();
    }

    private function advanceAfterAsset(LoanApplication $application, array $state, CustomerAsset $asset): array
    {
        $ab = $this->assetBackedFeeProduct();
        abort_unless($ab, 422, 'Asset-backed fee product (AB) is not configured.');

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

        $state['fee_paid_at'] = $state['fee_paid_at'] ?? now()->toIso8601String();

        return $this->advanceAfterFee($application, $state, $asset);
    }

    /**
     * After the AB fee delta is settled (or already covered), collect valuation fee then secure.
     * Insurance / GPS / ownership transfer happen after offer acceptance (post-approval).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function advanceAfterFee(LoanApplication $application, array $state, CustomerAsset $asset): array
    {
        $ab = $this->assetBackedFeeProduct();
        abort_unless($ab, 422, 'Asset-backed fee product (AB) is not configured.');

        $quote = $this->feeQuote($application);
        $state['insurance'] = $this->insuranceCheck($application, $asset);

        $valuationDue = (int) quoted_valuation_fee($application->customer);
        $state['valuation_fee_due'] = $valuationDue;

        if ($valuationDue > 0 && empty($state['valuation_fee_paid_at'])) {
            $state['status'] = self::STATUS_AWAITING_VALUATION_FEE;
            $this->saveState($application, $state);

            if ($application->customer) {
                app(NotificationService::class)->notifyInApp(
                    $application->customer,
                    __('borrower.collateral_secure.notify_valuation_fee_body', [
                        'amount' => format_money($valuationDue),
                    ]),
                    category: 'loan_application',
                    template: 'collateral_secure_valuation_fee',
                    title: __('borrower.collateral_secure.notify_valuation_fee_title'),
                    actionUrl: route('site.borrower.application', $application),
                    actionLabel: __('borrower.collateral_secure.cta_pay_valuation'),
                );
            }

            return $state;
        }

        return $this->finalizeSecured($application, $state, $asset, $ab, $quote);
    }

    public function markValuationFeePaid(LoanApplication $application): array
    {
        $state = $this->state($application);
        abort_unless($state, 422, 'No collateral request.');

        if (($state['status'] ?? '') === self::STATUS_SECURED && filled($state['valuation_fee_paid_at'] ?? null)) {
            return $state;
        }

        $this->healValuationFeeGate($application);
        $application->refresh();
        $state = $this->requireOpen($application);
        abort_unless(($state['status'] ?? '') === self::STATUS_AWAITING_VALUATION_FEE, 422);

        $asset = CustomerAsset::query()->find($state['customer_asset_id'] ?? 0);
        abort_unless($asset, 422);

        $state['valuation_fee_paid_at'] = now()->toIso8601String();
        $state['valuation_fee_due'] = 0;

        if (($state['path'] ?? self::PATH_SECURE) === self::PATH_SCREENING_VALUATION) {
            $state['status'] = self::STATUS_AWAITING_VALUER;
            $this->saveState($application, $state);
            LoanApplicationAsset::query()
                ->where('loan_application_id', $application->id)
                ->where('customer_asset_id', $asset->id)
                ->update(['valuation_fee_paid_at' => now()]);
            app(ValuationPartnerService::class)->autoAssignIfPossible($application->fresh());

            return $state;
        }

        $ab = $this->assetBackedFeeProduct();
        abort_unless($ab, 422);

        $state = $this->finalizeSecured($application, $state, $asset, $ab, $this->feeQuote($application) ?? []);

        app(ValuationPartnerService::class)->autoAssignIfPossible($application->fresh());

        return $state;
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
                    'valuation_fee_paid_at' => ! empty($state['valuation_fee_paid_at'])
                        ? Carbon::parse($state['valuation_fee_paid_at'])
                        : now(),
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

    private function isGroupFile(LoanApplication $application): bool
    {
        $application->loadMissing('product');
        if (filled($application->loan_group_id)) {
            return true;
        }

        return app(GroupLendingService::class)->isGroupProduct($application->product);
    }

    /** @return \Illuminate\Support\Collection<int, mixed> */
    private function activeNonLeaderMembers(LoanApplication $application)
    {
        $application->loadMissing('loanGroup.members.customer');

        return collect($application->loanGroup?->members ?? [])
            ->filter(function ($member) use ($application) {
                $role = strtolower((string) ($member->role ?? 'member'));
                if ($role === 'leader' || (int) $member->customer_id === (int) $application->customer_id) {
                    return false;
                }
                if (($member->member_status ?? 'active') !== 'active') {
                    return false;
                }

                return (int) $member->customer_id > 0 && $member->customer;
            })
            ->values();
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
