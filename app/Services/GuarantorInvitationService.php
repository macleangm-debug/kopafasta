<?php

namespace App\Services;

use App\Models\ApplicationStageHistory;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Support\MemberNumberFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuarantorInvitationService
{
    public function findCustomerByMemberNumber(string $membershipId): ?Customer
    {
        $key = MemberNumberFormatter::lookupKey($membershipId);

        if (! $key) {
            return null;
        }

        return Customer::query()
            ->where('member_no', $key)
            ->first();
    }

    public function findMemberByNumber(string $membershipId): ?Customer
    {
        $customer = $this->findCustomerByMemberNumber($membershipId);

        if (! $customer || ! $this->isEligibleInternalGuarantor($customer)) {
            return null;
        }

        return $customer;
    }

    public function findMemberCustomerByPhone(string $phone): ?Customer
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $normalized) ?? '';
        $suffix = strlen($digits) >= 9 ? substr($digits, -9) : $digits;

        $customer = Customer::query()
            ->where(function ($query) use ($normalized, $digits, $suffix) {
                $query->where('phone', $normalized)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'like', '%'.$suffix);
            })
            ->first();

        if (! $customer || ! ($customer->isMembershipActive() || $customer->isMembershipInGrace())) {
            return null;
        }

        return $customer;
    }

    public function isEligibleInternalGuarantor(Customer $customer): bool
    {
        return $customer->isMembershipActive() || $customer->isMembershipInGrace();
    }

    /**
     * @return array{ok: bool, message: string, name?: string, label?: string, member?: Customer}
     */
    public function verifyInternalMember(Customer $borrower, string $membershipId, string $phone, string $name): array
    {
        $member = $this->findCustomerByMemberNumber($membershipId);
        if (! $member) {
            return [
                'ok' => false,
                'message' => __('borrower.apply.alerts.guarantor_not_found'),
            ];
        }

        if (! $member->isMembershipActive() && ! $member->isMembershipInGrace()) {
            return [
                'ok' => false,
                'message' => __('borrower.apply.alerts.guarantor_not_member'),
            ];
        }

        if (! $member->isMembershipActive() && ! $member->isMembershipInGrace()) {
            return [
                'ok' => false,
                'message' => __('borrower.apply.alerts.guarantor_membership_inactive'),
            ];
        }

        if ($member->id === $borrower->id) {
            return [
                'ok' => false,
                'message' => __('borrower.apply.alerts.guarantor_self'),
            ];
        }

        $inputPhone = $this->normalizePhone($phone);
        $memberPhone = $this->normalizePhone($member->phone);
        if ($inputPhone === '' || $memberPhone === '' || $inputPhone !== $memberPhone) {
            return [
                'ok' => false,
                'message' => __('borrower.apply.alerts.guarantor_phone_mismatch'),
            ];
        }

        // Name is optional on the wizard form — membership + phone identify the member.
        // When a name is supplied (e.g. previous-guarantor re-verify), still check it.
        if (trim($name) !== '' && ! $this->namesMatch($name, $member)) {
            return [
                'ok' => false,
                'message' => __('borrower.apply.alerts.guarantor_name_mismatch'),
            ];
        }

        $displayName = trim(($member->first_name ?? '').' '.($member->last_name ?? ''));
        $statusKey = $member->isMembershipActive()
            ? 'active'
            : ($member->isMembershipInGrace() ? 'grace' : 'inactive');

        return [
            'ok' => true,
            'message' => __('borrower.apply.alerts.guarantor_verified'),
            'name' => $displayName,
            'label' => trim($displayName.' · '.__('borrower.apply.guarantor_fields.membership_'.$statusKey)),
            'member' => $member,
        ];
    }

    public function invitationUrl(GuarantorInvitation $invitation): string
    {
        $base = app(ReferralService::class)->appBaseUrl();

        return $base.'/guarantor-request/'.$invitation->token;
    }

    public function shortInvitationUrl(GuarantorInvitation $invitation): string
    {
        $code = $this->ensureShortCode($invitation);
        $base = rtrim((string) (config('guarantor.short_link_base') ?: app(ReferralService::class)->appBaseUrl()), '/');
        $path = rtrim((string) config('guarantor.short_link_path', '/g'), '/');

        return $base.$path.'/'.$code;
    }

    public function invitationMessage(GuarantorInvitation $invitation): string
    {
        $invitation->loadMissing(['borrower.user', 'application.product']);
        $locale = app()->getLocale();
        $borrowerPrefs = $invitation->borrower?->user?->preferences ?? [];
        if (filled($borrowerPrefs['preferred_locale'] ?? null)) {
            $locale = (string) $borrowerPrefs['preferred_locale'];
        }

        $previous = app()->getLocale();
        app()->setLocale($locale);
        try {
            $context = $this->invitationLoanContext($invitation);
            $url = $this->shortInvitationUrl($invitation);
            $guarantorName = trim((string) ($invitation->invitee_name ?: 'there'));
            $borrowerName = trim((string) ($context['borrower_name'] !== '—'
                ? $context['borrower_name']
                : (($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? ''))));

            return __('borrower.guarantor_invite.message', [
                'guarantor_name' => $guarantorName,
                'borrower_name' => $borrowerName,
                'product' => $context['product_name'],
                'amount' => $context['amount_label'],
                'duration' => $context['duration_label'],
                'frequency' => $context['repayment_frequency_label'],
                'installment' => $context['installment_label'],
                'link' => $url,
            ]);
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * Canonical accepted-quote context for guarantor invitation surfaces.
     *
     * @return array{
     *   amount: int,
     *   amount_label: string,
     *   tenure_months: int,
     *   duration_label: string,
     *   product_name: string,
     *   installment: float,
     *   installment_label: string,
     *   repayment_cadence: string,
     *   repayment_frequency_label: string,
     *   application_reference: string,
     *   borrower_name: string,
     *   fingerprint: string
     * }
     */
    public function invitationLoanContext(GuarantorInvitation $invitation): array
    {
        $invitation->loadMissing(['application.product', 'product', 'borrower']);
        $application = $this->authoritativeApplicationForInvitation($invitation);
        $product = $application?->product ?: $this->resolveInvitationProduct($invitation);
        $draftQuote = $this->draftQuoteForInvitation($invitation);

        // Prefer the submitted application quote — never invent a second figure set.
        $amount = (int) ($application?->requested_amount
            ?: $invitation->requested_amount
            ?: ($draftQuote['amount'] ?? 0));
        $tenure = (int) ($application?->requested_tenure_months
            ?: $invitation->requested_tenure_months
            ?: ($draftQuote['tenure'] ?? 0));

        $productName = trim((string) ($product?->localizedName() ?? ''));
        $cadence = $product
            ? app(GroupLendingService::class)->effectiveRepaymentCadence($product)
            : (string) ($draftQuote['cadence'] ?? 'monthly');
        if (! in_array($cadence, ['weekly', 'monthly'], true)) {
            $cadence = 'monthly';
        }

        $installment = 0.0;
        $installmentLabel = __('borrower.guarantor_invite.installment_tbd');
        if ($amount > 0 && $tenure > 0 && $product) {
            $monthlyRate = app(DisplayedRateService::class)->displayedMonthlyRate($product, (float) $amount);
            $method = in_array(($product->interest_method ?? 'reducing'), ['flat', 'reducing'], true)
                ? (string) ($product->interest_method ?? 'reducing')
                : 'reducing';
            $preview = app(RepaymentScheduleGenerator::class)->preview($amount, $monthlyRate, $tenure, $cadence, null, $method);
            $first = $preview[0] ?? null;
            if ($first) {
                $installment = round((float) $first['total_due'], 2);
                $installmentLabel = 'TZS '.number_format($installment);
            }
        }

        $borrowerName = trim((string) (
            ($invitation->borrower?->legalDisplayName() ?? null)
            ?: trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? ''))
        ));
        $reference = (string) (
            $application?->application_number
            ?? $application?->draft_reference
            ?? $draftQuote['draft_reference']
            ?? ($invitation->short_code ? strtoupper((string) $invitation->short_code) : '')
        );

        $frequencyLabel = $cadence === 'weekly'
            ? __('site.product_detail.repayment_weekly')
            : __('site.product_detail.repayment_monthly');

        $context = [
            'amount' => $amount,
            'amount_label' => $amount > 0 ? 'TZS '.number_format($amount) : __('borrower.guarantor_invite.amount_tbd'),
            'tenure_months' => $tenure,
            'duration_label' => $tenure > 0
                ? trans_choice('borrower.guarantor_invite.duration_months', $tenure, ['count' => $tenure])
                : __('borrower.guarantor_invite.duration_tbd'),
            'product_name' => $productName !== '' ? $productName : __('borrower.guarantor_invite.product_tbd'),
            'installment' => $installment,
            'installment_label' => $installmentLabel,
            'repayment_cadence' => $cadence,
            'repayment_frequency_label' => $frequencyLabel,
            'application_reference' => $reference !== '' ? $reference : '—',
            'borrower_name' => $borrowerName !== '' ? $borrowerName : '—',
        ];
        $context['fingerprint'] = $this->quoteFingerprint($context);

        return $context;
    }

    /**
     * Submitted application quote is authoritative for guarantor consent surfaces.
     * Linked invitation first; otherwise borrower's open awaiting-guarantor file for the product.
     */
    protected function authoritativeApplicationForInvitation(GuarantorInvitation $invitation): ?LoanApplication
    {
        if ($invitation->application) {
            return $invitation->application;
        }

        if ($invitation->loan_application_id) {
            return LoanApplication::query()
                ->with('product')
                ->find($invitation->loan_application_id);
        }

        if (! $invitation->customer_id) {
            return null;
        }

        return LoanApplication::query()
            ->with('product')
            ->where('customer_id', $invitation->customer_id)
            ->when($invitation->loan_product_id, fn ($q) => $q->where('loan_product_id', $invitation->loan_product_id))
            ->whereNotIn('status', LoanApplication::CLOSED_STATUSES)
            ->where(function ($q) {
                $q->where('status', 'awaiting_guarantor')
                    ->orWhere('current_stage', 'awaiting_guarantor');
            })
            ->latest('id')
            ->first();
    }

    /** @return array{amount?: int, tenure?: int, cadence?: string, draft_reference?: string} */
    protected function draftQuoteForInvitation(GuarantorInvitation $invitation): array
    {
        $drafts = LoanApplicationDraft::query()
            ->where('customer_id', $invitation->customer_id)
            ->when($invitation->loan_product_id, fn ($q) => $q->where('loan_product_id', $invitation->loan_product_id))
            ->latest('id')
            ->get();

        foreach ($drafts as $draft) {
            $payload = $draft->payload ?? [];
            $invitationId = (int) ($payload['external_guarantor']['invitation_id']
                ?? $payload['internal_guarantor']['invitation_id']
                ?? 0);
            if ($invitation->loan_application_id === null && $invitationId !== 0 && $invitationId !== (int) $invitation->id) {
                continue;
            }

            $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
            $amount = (int) ($form['requested_amount'] ?? 0);
            $tenure = (int) ($form['requested_tenure_months'] ?? 0);
            if ($amount <= 0 && $tenure <= 0 && blank($draft->draft_reference)) {
                continue;
            }

            $product = $draft->product ?? ($draft->loan_product_id ? LoanProduct::query()->find($draft->loan_product_id) : null);

            return array_filter([
                'amount' => $amount > 0 ? $amount : null,
                'tenure' => $tenure > 0 ? $tenure : null,
                'cadence' => $product
                    ? app(GroupLendingService::class)->effectiveRepaymentCadence($product)
                    : null,
                'draft_reference' => $draft->draft_reference ?: ($payload['draft_reference'] ?? null),
            ], fn ($value) => $value !== null && $value !== '');
        }

        return [];
    }

    /** @param  array<string, mixed>  $context */
    public function quoteFingerprint(array $context): string
    {
        return hash('sha256', implode('|', [
            (int) ($context['amount'] ?? 0),
            (int) ($context['tenure_months'] ?? 0),
            (string) ($context['repayment_cadence'] ?? ''),
            round((float) ($context['installment'] ?? 0), 2),
            mb_strtolower(trim((string) ($context['product_name'] ?? ''))),
        ]));
    }

    public function materialQuoteChanged(GuarantorInvitation $invitation, ?array $current = null): bool
    {
        $snapshot = is_array($invitation->consent_snapshot) ? $invitation->consent_snapshot : null;
        if (! $snapshot || empty($snapshot['fingerprint'])) {
            return false;
        }

        $current ??= $this->invitationLoanContext($invitation);
        if ((int) ($current['amount'] ?? 0) <= 0 || (int) ($current['tenure_months'] ?? 0) <= 0) {
            return false;
        }

        return (string) $snapshot['fingerprint'] !== (string) ($current['fingerprint'] ?? '');
    }

    /**
     * Push latest quote onto the invitation; if the guarantor already consented and the quote
     * changed materially, supersede confirmation and require reconfirmation (profile/signature stay).
     */
    public function syncInvitationQuote(GuarantorInvitation $invitation, ?int $amount = null, ?int $tenure = null, ?int $loanProductId = null): GuarantorInvitation
    {
        $updates = [];
        if ($amount !== null && $amount > 0) {
            $updates['requested_amount'] = $amount;
        }
        if ($tenure !== null && $tenure > 0) {
            $updates['requested_tenure_months'] = $tenure;
        }
        if ($loanProductId !== null && $loanProductId > 0) {
            $updates['loan_product_id'] = $loanProductId;
        }

        if ($updates !== []) {
            $invitation->update($updates);
            $invitation = $invitation->fresh(['application.product', 'product', 'borrower', 'customerGuarantor']);
        }

        // Keep the linked application quote in step while still on guarantor hold / pre-screen.
        if ($invitation->application
            && in_array((string) $invitation->application->status, ['awaiting_guarantor', 'draft', 'submitted'], true)
            && ($amount !== null || $tenure !== null || $loanProductId !== null)
        ) {
            $appUpdates = [];
            if ($amount !== null && $amount > 0) {
                $appUpdates['requested_amount'] = $amount;
            }
            if ($tenure !== null && $tenure > 0) {
                $appUpdates['requested_tenure_months'] = $tenure;
            }
            if ($loanProductId !== null && $loanProductId > 0) {
                $appUpdates['loan_product_id'] = $loanProductId;
            }
            if ($appUpdates !== []) {
                $invitation->application->update($appUpdates);
                $invitation = $invitation->fresh(['application.product', 'product', 'borrower', 'customerGuarantor']);
            }
        }

        if ($this->hasRecordedConsent($invitation) && $this->materialQuoteChanged($invitation)) {
            $this->markConsentPendingReconfirmation($invitation);
        }

        return $invitation->fresh(['application.product', 'product', 'borrower', 'customerGuarantor']);
    }

    public function hasRecordedConsent(GuarantorInvitation $invitation): bool
    {
        if (is_array($invitation->consent_snapshot) && ! empty($invitation->consent_snapshot['fingerprint'])) {
            return true;
        }

        return in_array((string) $invitation->status, ['accepted'], true)
            || $invitation->customerGuarantor?->status === 'approved';
    }

    public function recordConsentSnapshot(GuarantorInvitation $invitation): void
    {
        $context = $this->invitationLoanContext($invitation);
        $invitation->update([
            'consent_snapshot' => array_merge($context, [
                'confirmed_at' => now()->toIso8601String(),
            ]),
            'confirmation_status' => GuarantorInvitation::CONFIRMATION_CONFIRMED,
        ]);
    }

    public function markConsentPendingReconfirmation(GuarantorInvitation $invitation): void
    {
        $invitation->refresh();
        if ($invitation->needsQuoteReconfirmation() && ! $this->materialQuoteChanged($invitation)) {
            return;
        }

        $prior = is_array($invitation->consent_snapshot) ? $invitation->consent_snapshot : null;
        $history = is_array($invitation->consent_history) ? $invitation->consent_history : [];
        if ($prior) {
            $history[] = array_merge($prior, [
                'superseded_at' => now()->toIso8601String(),
                'confirmation_status' => GuarantorInvitation::CONFIRMATION_SUPERSEDED,
            ]);
        }

        $current = $this->invitationLoanContext($invitation);
        $invitation->update([
            'consent_history' => $history,
            'confirmation_status' => GuarantorInvitation::CONFIRMATION_PENDING_RECONFIRMATION,
            // Keep accepted/approved — only confirmation of revised quote is outstanding.
            'status' => in_array((string) $invitation->status, ['accepted', 'pending'], true)
                ? (string) $invitation->status
                : 'accepted',
        ]);

        app(AuditService::class)->log(null, 'guarantor_invitation.consent_superseded', $invitation, [
            'confirmation_status' => GuarantorInvitation::CONFIRMATION_CONFIRMED,
            'consent_snapshot' => $prior,
        ], [
            'confirmation_status' => GuarantorInvitation::CONFIRMATION_PENDING_RECONFIRMATION,
            'prior_consent' => $prior,
            'revised_quote' => $current,
            'reason' => 'material_quote_change',
        ]);

        $this->notifyGuarantorQuoteRevised($invitation->fresh(['borrower', 'application', 'customerGuarantor', 'guarantorCustomer']), $prior, $current);
    }

    public function reconfirmConsent(GuarantorInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $this->recordConsentSnapshot($invitation);
            $invitation->update([
                'responded_at' => now(),
                'status' => 'accepted',
            ]);

            if ($link = $invitation->customerGuarantor) {
                if ($link->status !== 'approved') {
                    $link->update(['status' => 'approved']);
                }
            }
        });

        $invitation = $invitation->fresh(['customerGuarantor', 'application']);
        app(AuditService::class)->log(null, 'guarantor_invitation.consent_reconfirmed', $invitation, [
            'confirmation_status' => GuarantorInvitation::CONFIRMATION_PENDING_RECONFIRMATION,
        ], [
            'confirmation_status' => GuarantorInvitation::CONFIRMATION_CONFIRMED,
            'consent_snapshot' => $invitation->consent_snapshot,
        ]);

        if ($link = $invitation->customerGuarantor) {
            $this->tryReleaseApplicationFromGuarantorHold($link->application ?? $invitation->application);
        }
    }

    /** @return array{previous: ?array, current: array, needs_reconfirmation: bool} */
    public function quoteComparison(GuarantorInvitation $invitation): array
    {
        $current = $this->invitationLoanContext($invitation);
        $previous = null;
        if ($invitation->needsQuoteReconfirmation() && is_array($invitation->consent_snapshot)) {
            $previous = $invitation->consent_snapshot;
        } elseif (is_array($invitation->consent_history) && $invitation->consent_history !== []) {
            $previous = end($invitation->consent_history) ?: null;
        }

        return [
            'previous' => is_array($previous) ? $previous : null,
            'current' => $current,
            'needs_reconfirmation' => $invitation->needsQuoteReconfirmation(),
        ];
    }

    protected function notifyGuarantorQuoteRevised(GuarantorInvitation $invitation, ?array $previous, array $current): void
    {
        $guarantor = $invitation->guarantorCustomer;
        if (! $guarantor && $invitation->type === 'internal' && $invitation->guarantor_customer_id) {
            $guarantor = Customer::query()->find($invitation->guarantor_customer_id);
        }

        $borrowerName = $current['borrower_name'] ?? trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? ''));
        $reference = $current['application_reference'] ?? '—';
                $actionUrl = $invitation->type === 'internal' && $invitation->customer_guarantor_id
            ? route('site.borrower.guarantor-requests.show', $invitation->customer_guarantor_id)
            : route('site.guarantor.show', $invitation->token);

        $message = __('borrower.guarantor_invite.quote_revised_body', [
            'borrower' => $borrowerName,
            'reference' => $reference,
            'amount' => $current['amount_label'] ?? '—',
            'duration' => $current['duration_label'] ?? '—',
        ]);

        if ($guarantor) {
            app(NotificationService::class)->notifyInApp(
                $guarantor,
                $message,
                'guarantor',
                'guarantor_quote_revised',
                __('borrower.guarantor_invite.quote_revised_title'),
                $actionUrl,
                __('borrower.guarantor_invite.reconfirm_cta'),
                [
                    'title_key' => 'borrower.guarantor_invite.quote_revised_title',
                    'body_key' => 'borrower.guarantor_invite.quote_revised_body',
                    'params' => [
                        'borrower' => $borrowerName,
                        'reference' => $reference,
                        'amount' => $current['amount_label'] ?? '—',
                        'duration' => $current['duration_label'] ?? '—',
                    ],
                    'customer_guarantor_id' => $invitation->customer_guarantor_id,
                ],
            );
        } elseif ($invitation->contact) {
            app(NotificationService::class)->sendSms(
                (string) $invitation->contact,
                $message.' '.$actionUrl,
                null,
                'guarantor_quote_revised',
            );
        }
    }

    public function resolveInvitationProduct(GuarantorInvitation $invitation): ?LoanProduct
    {
        if ($product = $invitation->application?->product) {
            return $product;
        }

        if ($invitation->relationLoaded('product') && $invitation->product) {
            return $invitation->product;
        }

        if ($invitation->loan_product_id) {
            return LoanProduct::query()->find($invitation->loan_product_id);
        }

        return $this->productFromBorrowerDraft($invitation);
    }

    protected function productFromBorrowerDraft(GuarantorInvitation $invitation): ?LoanProduct
    {
        $drafts = LoanApplicationDraft::query()
            ->where('customer_id', $invitation->customer_id)
            ->with('product')
            ->get();

        foreach ($drafts as $draft) {
            $payload = $draft->payload ?? [];
            $invitationId = (int) ($payload['external_guarantor']['invitation_id']
                ?? $payload['internal_guarantor']['invitation_id']
                ?? 0);
            if ($invitationId !== (int) $invitation->id) {
                continue;
            }

            return $draft->product ?? ($draft->loan_product_id
                ? LoanProduct::query()->find($draft->loan_product_id)
                : null);
        }

        return null;
    }

    /**
     * Borrower-visible guarantor progress (member + non-member).
     *
     * Codes:
     * - invitation_sent / pending_acceptance — waiting for Accept/Decline
     * - registration_in_progress / kyc_in_progress — external onboarding
     * - pending_profile — accepted, but guarantor profile not complete
     * - ready — accepted and profile complete (guarantor side ready)
     * - rejected / expired
     *
     * @return array{
     *   code: string,
     *   label: string,
     *   profile_percent: int|null,
     *   accepted: bool,
     *   ready: bool,
     *   steps: list<array{key: string, label: string, complete: bool, current: bool}>
     * }
     */
    public function borrowerInvitationStatus(GuarantorInvitation $invitation): array
    {
        $invitation->loadMissing('customerGuarantor');
        $link = $invitation->customerGuarantor;
        $guarantorCustomer = $invitation->guarantor_customer_id
            ? Customer::find($invitation->guarantor_customer_id)
            : null;

        if ($invitation->status === 'rejected' || $link?->status === 'rejected') {
            return $this->borrowerStatusPayload('rejected', null, false, false);
        }

        if ($invitation->status === 'expired') {
            return $this->borrowerStatusPayload('expired', null, false, false);
        }

        if ($invitation->needsQuoteReconfirmation()) {
            $profilePercent = null;
            if ($guarantorCustomer) {
                $profile = app(GuarantorOnboardingService::class)->guarantorProfileStatus($guarantorCustomer);
                $profilePercent = (int) ($profile['percent'] ?? 0);
            }

            return $this->borrowerStatusPayload('pending_reconfirmation', $profilePercent, true, false);
        }

        $accepted = $link?->status === 'approved'
            || in_array((string) $invitation->status, ['accepted'], true);

        $profilePercent = null;
        $profileMet = false;
        if ($guarantorCustomer) {
            $profile = app(GuarantorOnboardingService::class)->guarantorProfileStatus($guarantorCustomer);
            $profilePercent = (int) ($profile['percent'] ?? 0);
            $profileMet = (bool) ($profile['met'] ?? false);
        }

        // Accepted guarantee — profile is the next gate (internal + external).
        if ($link?->status === 'approved') {
            if (! $profileMet) {
                return $this->borrowerStatusPayload('pending_profile', $profilePercent, true, false);
            }

            return $this->borrowerStatusPayload('ready', $profilePercent ?? 100, true, true);
        }

        // Still waiting for Accept / Decline.
        if ($invitation->status === 'pending') {
            return $this->borrowerStatusPayload(
                $invitation->type === 'internal' ? 'pending_acceptance' : 'invitation_sent',
                $profilePercent,
                false,
                false,
            );
        }

        // External (or rare) mid-flow: invitation accepted but link not approved yet.
        if ($invitation->status === 'accepted') {
            if (! $guarantorCustomer) {
                return $this->borrowerStatusPayload('registration_in_progress', null, true, false);
            }

            if (MembershipService::isRequiredForCountry($guarantorCustomer->country_code ?? null)
                && ! $guarantorCustomer->isMembershipActive()
                && ! $guarantorCustomer->isMembershipInGrace()) {
                return $this->borrowerStatusPayload('registration_in_progress', $profilePercent, true, false);
            }

            if (! $profileMet) {
                return $this->borrowerStatusPayload('pending_profile', $profilePercent, true, false);
            }

            return $this->borrowerStatusPayload('guarantee_pending', $profilePercent, true, false);
        }

        return $this->borrowerStatusPayload('invitation_sent', $profilePercent, $accepted, false);
    }

    /**
     * @return array{
     *   code: string,
     *   label: string,
     *   profile_percent: int|null,
     *   accepted: bool,
     *   ready: bool,
     *   steps: list<array{key: string, label: string, complete: bool, current: bool}>
     * }
     */
    private function borrowerStatusPayload(string $code, ?int $profilePercent, bool $accepted, bool $ready): array
    {
        // Badge follows furthest achieved/action state — never stay on “invitation sent” after accept.
        $labelKey = match ($code) {
            'pending_acceptance', 'invitation_sent' => 'invitation_sent',
            'accepted', 'registration_in_progress' => 'invitation_accepted',
            'pending_profile', 'kyc_in_progress', 'guarantee_pending', 'pending_reconfirmation' => 'profile_in_progress',
            'ready' => 'ready_for_review',
            'rejected' => 'rejected',
            'expired' => 'expired',
            default => 'invitation_sent',
        };
        if (in_array($code, ['registration_in_progress', 'accepted'], true)
            && $profilePercent !== null
            && $profilePercent > 0
            && ! $ready) {
            $labelKey = 'profile_in_progress';
        }

        $invitedDone = true;
        $acceptedDone = $accepted || $ready || in_array($code, [
            'pending_profile',
            'guarantee_pending',
            'ready',
            'pending_reconfirmation',
            'registration_in_progress',
            'kyc_in_progress',
            'accepted',
        ], true);
        $profileDone = $ready || ($acceptedDone && $profilePercent !== null && $profilePercent >= 100 && $code === 'ready');
        if ($code === 'ready') {
            $profileDone = true;
        }
        if ($code === 'pending_profile' || $code === 'pending_reconfirmation') {
            $profileDone = $code === 'pending_reconfirmation';
        }

        $current = match ($code) {
            'pending_acceptance', 'invitation_sent' => 'accepted',
            'registration_in_progress', 'kyc_in_progress', 'pending_profile', 'guarantee_pending', 'pending_reconfirmation', 'accepted' => 'profile',
            'ready' => 'ready',
            'rejected', 'expired' => 'accepted',
            default => 'accepted',
        };

        if (in_array($code, ['pending_acceptance', 'invitation_sent'], true)) {
            $current = 'accepted';
            $acceptedDone = false;
            $profileDone = false;
        }

        $steps = [
            [
                'key' => 'invited',
                'label' => __('borrower.apply.guarantor_progress.invited'),
                'complete' => $invitedDone,
                'current' => false,
            ],
            [
                'key' => 'accepted',
                'label' => __('borrower.apply.guarantor_progress.accepted'),
                'complete' => $acceptedDone,
                'current' => $current === 'accepted',
            ],
            [
                'key' => 'profile',
                'label' => $profilePercent !== null
                    ? __('borrower.apply.guarantor_progress.profile_pct', ['percent' => $profilePercent])
                    : __('borrower.apply.guarantor_progress.profile'),
                'complete' => $profileDone || $ready,
                'current' => $current === 'profile',
            ],
            [
                'key' => 'ready',
                'label' => __('borrower.apply.guarantor_progress.ready'),
                'complete' => $ready,
                'current' => $current === 'ready',
            ],
        ];

        return [
            'code' => $code,
            'label' => __('borrower.apply.guarantor_status.'.$labelKey),
            'profile_percent' => $profilePercent,
            'accepted' => $acceptedDone,
            'ready' => $ready,
            'steps' => $steps,
        ];
    }

    public function guarantorLinkStatusLabel(CustomerGuarantor $link): string
    {
        return $this->workflowStatusLabel($link);
    }

    public function underwritingGuarantorStatusLabel(CustomerGuarantor $link): string
    {
        return $this->workflowStatusLabel($link);
    }

    public function invitationWorkflowStatusLabel(GuarantorInvitation $invitation): string
    {
        return $this->borrowerInvitationStatus($invitation)['label'];
    }

    /** @return array{code: string, label: string} */
    public function workflowStatus(CustomerGuarantor $link, ?GuarantorInvitation $invitation = null): array
    {
        $invitation ??= GuarantorInvitation::query()
            ->where('customer_guarantor_id', $link->id)
            ->latest()
            ->first();

        if ($invitation) {
            $status = $this->borrowerInvitationStatus($invitation);

            return ['code' => $status['code'], 'label' => $status['label']];
        }

        if ($link->status === 'approved') {
            return ['code' => 'ready', 'label' => __('borrower.apply.guarantor_status.ready')];
        }

        if ($link->status === 'rejected') {
            return ['code' => 'rejected', 'label' => __('borrower.apply.guarantor_status.rejected')];
        }

        return ['code' => 'invitation_sent', 'label' => __('borrower.apply.guarantor_status.invitation_sent')];
    }

    public function workflowStatusLabel(CustomerGuarantor $link, ?GuarantorInvitation $invitation = null): string
    {
        return $this->workflowStatus($link, $invitation)['label'];
    }

    public function whatsAppShareUrl(GuarantorInvitation $invitation, Customer $borrower): ?string
    {
        if (! $invitation->contact) {
            return null;
        }

        $phone = $this->sharePhoneDigits($invitation->contact);
        if ($phone === '') {
            return null;
        }

        return 'https://wa.me/'.$phone.'?text='.urlencode($this->invitationMessage($invitation));
    }

    public function smsShareUrl(GuarantorInvitation $invitation): ?string
    {
        $phone = $this->sharePhoneDigits($invitation->contact);
        if ($phone === '') {
            return null;
        }

        return 'sms:+'.$phone.'?body='.urlencode($this->invitationMessage($invitation));
    }

    public function emailShareUrl(GuarantorInvitation $invitation): ?string
    {
        $invitation->loadMissing('customerGuarantor.guarantor');
        $email = trim((string) ($invitation->customerGuarantor?->guarantor?->email ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            $email = trim((string) $invitation->contact);
        }
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        $invitation->loadMissing('borrower');
        $borrowerName = trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? ''));
        $subject = __('borrower.guarantor_invite.email_subject', ['borrower' => $borrowerName]);
        $body = $this->invitationMessage($invitation);

        return 'mailto:'.$email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($body);
    }

    /** @return array{invitation_id: int, invitation_url: string, short_url: string, whatsapp_url: string|null, sms_url: string|null, email_url: string|null, status: string, borrower_status_code: string, borrower_status_label: string, profile_percent: int|null, accepted: bool, ready: bool, steps: list<array{key: string, label: string, complete: bool, current: bool}>} */
    public function sharePayload(GuarantorInvitation $invitation, ?Customer $borrower = null): array
    {
        $borrower ??= $invitation->borrower;
        $borrowerStatus = $this->borrowerInvitationStatus($invitation);

        return [
            'invitation_id' => $invitation->id,
            'invitation_url' => $this->invitationUrl($invitation),
            'short_url' => $this->shortInvitationUrl($invitation),
            'whatsapp_url' => $this->whatsAppShareUrl($invitation, $borrower),
            'sms_url' => $this->smsShareUrl($invitation),
            'email_url' => $this->emailShareUrl($invitation),
            'status' => (string) ($invitation->status ?? 'pending'),
            'borrower_status_code' => $borrowerStatus['code'],
            'borrower_status_label' => $borrowerStatus['label'],
            'profile_percent' => $borrowerStatus['profile_percent'],
            'accepted' => $borrowerStatus['accepted'],
            'ready' => $borrowerStatus['ready'],
            'steps' => $borrowerStatus['steps'],
        ];
    }

    /**
     * Create or refresh a pending external invitation before the loan application is submitted.
     *
     * @return array{invitation_id: int, invitation_url: string, short_url: string, whatsapp_url: string|null, sms_url: string|null, email_url: string|null}
     */
    public function prepareWizardExternalInvitation(
        Customer $borrower,
        string $firstName,
        ?string $middleName,
        string $lastName,
        string $phone,
        ?string $email,
        string $relationship,
        string $region,
        string $district,
        ?string $preferredChannel,
        ?int $existingInvitationId = null,
        ?int $requestedAmount = null,
        ?int $requestedTenureMonths = null,
        ?int $loanProductId = null,
        ?LoanApplication $application = null,
    ): array {
        $phone = $this->normalizePhone($phone);
        if ($member = $this->findMemberCustomerByPhone($phone)) {
            throw new \InvalidArgumentException(__('borrower.apply.alerts.guarantor_phone_is_member', [
                'name' => trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
            ]));
        }

        if ($application) {
            if ((int) $application->customer_id !== (int) $borrower->id) {
                throw new \InvalidArgumentException('Application does not belong to this borrower.');
            }
            $loanProductId = $loanProductId ?: (int) $application->loan_product_id;
            if (! $requestedAmount || $requestedAmount <= 0) {
                $requestedAmount = (int) $application->requested_amount;
            }
            if (! $requestedTenureMonths || $requestedTenureMonths <= 0) {
                $requestedTenureMonths = (int) $application->requested_tenure_months;
            }
        }

        $displayName = trim(collect([$firstName, $middleName, $lastName])->filter()->implode(' '));
        $address = trim(collect([$region, $district])->filter()->implode(', '));
        $channel = in_array($preferredChannel, ['whatsapp', 'sms', 'email'], true) ? $preferredChannel : 'whatsapp';
        $contact = $phone;

        return DB::transaction(function () use (
            $borrower,
            $firstName,
            $middleName,
            $lastName,
            $phone,
            $email,
            $relationship,
            $channel,
            $contact,
            $displayName,
            $address,
            $existingInvitationId,
            $requestedAmount,
            $requestedTenureMonths,
            $loanProductId,
            $application,
        ): array {
            app(LoanPolicyService::class)->expireSupersededGuarantorLinks($borrower, $existingInvitationId);

            $invitation = null;
            if ($existingInvitationId) {
                $invitationQuery = GuarantorInvitation::query()
                    ->where('id', $existingInvitationId)
                    ->where('customer_id', $borrower->id)
                    ->where('type', 'external')
                    ->whereIn('status', ['pending', 'accepted']);
                if ($application) {
                    $invitationQuery->where(function ($q) use ($application) {
                        $q->whereNull('loan_application_id')
                            ->orWhere('loan_application_id', $application->id);
                    });
                } else {
                    $invitationQuery->whereNull('loan_application_id');
                }
                $invitation = $invitationQuery->first();
            }

            $applicationId = $application?->id;

            if ($invitation?->customer_guarantor_id) {
                $link = CustomerGuarantor::query()->find($invitation->customer_guarantor_id);
                if ($link?->guarantor_id) {
                    Guarantor::query()->where('id', $link->guarantor_id)->update([
                        'first_name' => trim($firstName.' '.($middleName ?: '')),
                        'last_name' => $lastName,
                        'phone' => $phone,
                        'email' => $email,
                        'relationship' => $relationship,
                        'address' => $address,
                    ]);
                }
                if ($link && $applicationId && ! $link->loan_application_id) {
                    $link->update(['loan_application_id' => $applicationId]);
                }
                $identityChanged = $invitation->contact !== $contact
                    || $invitation->invitee_name !== $displayName;

                $updates = [
                    'channel' => $channel,
                    'contact' => $contact,
                    'invitee_name' => $displayName,
                    'requested_amount' => $requestedAmount,
                    'requested_tenure_months' => $requestedTenureMonths,
                    'loan_product_id' => $loanProductId,
                    'expires_at' => now()->addDays($this->invitationExpiryDays()),
                    'status' => 'pending',
                ];
                if ($applicationId) {
                    $updates['loan_application_id'] = $applicationId;
                }

                if ($identityChanged) {
                    $updates['token'] = Str::random(48);
                    $updates['short_code'] = $this->generateShortCode();
                }

                $hadConsent = $this->hasRecordedConsent($invitation);
                $invitation->update($updates);
                if ($hadConsent && ! $identityChanged) {
                    // Quote/contact refresh after prior consent — keep profile; require reconfirmation if quote moved.
                    $invitation->update(['status' => 'accepted']);
                    $this->syncInvitationQuote(
                        $invitation->fresh(),
                        $requestedAmount,
                        $requestedTenureMonths,
                        $loanProductId,
                    );
                }
            } else {
                $guarantor = Guarantor::create([
                    'first_name' => trim($firstName.' '.($middleName ?: '')),
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'email' => $email,
                    'relationship' => $relationship,
                    'address' => $address,
                ]);

                $link = CustomerGuarantor::create([
                    'customer_id' => $borrower->id,
                    'guarantor_id' => $guarantor->id,
                    'loan_application_id' => $applicationId,
                    'status' => 'pending',
                ]);

                $invitation = GuarantorInvitation::create([
                    'customer_id' => $borrower->id,
                    'loan_application_id' => $applicationId,
                    'loan_product_id' => $loanProductId,
                    'customer_guarantor_id' => $link->id,
                    'type' => 'external',
                    'channel' => $channel,
                    'contact' => $contact,
                    'invitee_name' => $displayName,
                    'requested_amount' => $requestedAmount,
                    'requested_tenure_months' => $requestedTenureMonths,
                    'token' => Str::random(48),
                    'short_code' => $this->generateShortCode(),
                    'status' => 'pending',
                    'expires_at' => now()->addDays($this->invitationExpiryDays()),
                ]);
            }

            $this->ensureShortCode($invitation);

            // Nomination only. Invitation is dispatched after the borrower first gate passes
            // (or immediately on guarantor-supplement Finish).

            return $this->sharePayload($invitation->fresh(['application.product', 'product', 'borrower']), $borrower);
        });
    }

    public function finalizeWizardExternalInvitation(
        Customer $borrower,
        LoanApplication $application,
        int $invitationId,
    ): GuarantorInvitation {
        $invitation = GuarantorInvitation::query()
            ->where('id', $invitationId)
            ->where('customer_id', $borrower->id)
            ->where('type', 'external')
            ->whereIn('status', ['pending', 'accepted'])
            ->where(function ($q) use ($application) {
                $q->whereNull('loan_application_id')
                    ->orWhere('loan_application_id', $application->id);
            })
            ->first();

        if (! $invitation) {
            throw new \InvalidArgumentException('External guarantor invitation not found or already linked.');
        }

        $link = CustomerGuarantor::query()->find($invitation->customer_guarantor_id);
        if (! $link) {
            throw new \InvalidArgumentException('External guarantor link not found.');
        }

        $link->update(['loan_application_id' => $application->id]);
        $invitation->update([
            'loan_application_id' => $application->id,
            'loan_product_id' => $invitation->loan_product_id ?: $application->loan_product_id,
            'requested_amount' => (int) $application->requested_amount,
            'requested_tenure_months' => (int) $application->requested_tenure_months,
        ]);

        $invitation = $this->syncInvitationQuote(
            $invitation->fresh(),
            (int) $application->requested_amount,
            (int) $application->requested_tenure_months,
            (int) $application->loan_product_id,
        );
        app(GuarantorSignatureService::class)->attachToApplication($invitation, $application, $link);

        return $invitation;
    }

    protected function sharePhoneDigits(?string $contact): string
    {
        $phone = preg_replace('/\D/', '', (string) $contact) ?? '';
        if ($phone === '') {
            return '';
        }
        if (str_starts_with($phone, '0')) {
            return '255'.substr($phone, 1);
        }
        if (! str_starts_with($phone, '255')) {
            return '255'.$phone;
        }

        return $phone;
    }

    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            return '+255'.substr($digits, 1);
        }
        if (str_starts_with($digits, '255')) {
            return '+'.$digits;
        }

        return '+255'.$digits;
    }

    public function attachInternal(
        Customer $borrower,
        LoanApplication $application,
        string $membershipId,
        string $phone,
        string $name,
    ): array {
        $verified = $this->verifyInternalMember($borrower, $membershipId, $phone, $name);
        if (! $verified['ok']) {
            throw new \InvalidArgumentException($verified['message']);
        }

        $member = $verified['member'];

        $requestedAmount = (float) ($application->requested_amount ?? 0);
        if ($message = app(LoanPolicyService::class)->canAcceptGuarantee($member, $requestedAmount > 0 ? $requestedAmount : null)) {
            throw new \InvalidArgumentException($message);
        }

        // Prefer linking a wizard invite already sent to this member.
        $existing = GuarantorInvitation::query()
            ->where('customer_id', $borrower->id)
            ->where('type', 'internal')
            ->where('guarantor_customer_id', $member->id)
            ->whereNull('loan_application_id')
            ->whereIn('status', ['pending', 'accepted'])
            ->latest('id')
            ->first();

        if ($existing) {
            return $this->finalizeWizardInternalInvitation($borrower, $application, (int) $existing->id);
        }

        return DB::transaction(function () use ($borrower, $application, $member, $membershipId): array {
            [$link, $invitation] = $this->createInternalInvitationRecords(
                $borrower,
                $member,
                $membershipId,
                $application->loan_product_id,
                (int) $application->requested_amount,
                (int) $application->requested_tenure_months,
                $application->id,
            );

            // Nomination only. Invitation is dispatched after the borrower first gate passes.

            return [$link, $invitation];
        });
    }

    /**
     * Send an in-app Accept/Decline request while the borrower is still in the apply wizard
     * (before application submit) — mirrors prepareWizardExternalInvitation for members.
     *
     * @return array{invitation_id: int, customer_guarantor_id: int, status: string, borrower_status_code: string, borrower_status_label: string, notified: bool, invitee_name: string}
     */
    public function prepareWizardInternalInvitation(
        Customer $borrower,
        string $membershipId,
        string $phone,
        string $name = '',
        ?int $existingInvitationId = null,
        ?int $requestedAmount = null,
        ?int $requestedTenureMonths = null,
        ?int $loanProductId = null,
    ): array {
        $verified = $this->verifyInternalMember($borrower, $membershipId, $phone, $name);
        if (! $verified['ok']) {
            throw new \InvalidArgumentException($verified['message']);
        }

        $member = $verified['member'];
        if ($message = app(LoanPolicyService::class)->canAcceptGuarantee($member, $requestedAmount && $requestedAmount > 0 ? (float) $requestedAmount : null)) {
            throw new \InvalidArgumentException($message);
        }

        return DB::transaction(function () use (
            $borrower,
            $member,
            $membershipId,
            $existingInvitationId,
            $requestedAmount,
            $requestedTenureMonths,
            $loanProductId,
        ): array {
            app(LoanPolicyService::class)->expireSupersededGuarantorLinks($borrower, $existingInvitationId);

            $invitation = null;
            if ($existingInvitationId) {
                $invitation = GuarantorInvitation::query()
                    ->where('id', $existingInvitationId)
                    ->where('customer_id', $borrower->id)
                    ->where('type', 'internal')
                    ->whereNull('loan_application_id')
                    ->whereIn('status', ['pending', 'accepted'])
                    ->first();
            }

            if (! $invitation) {
                $invitation = GuarantorInvitation::query()
                    ->where('customer_id', $borrower->id)
                    ->where('type', 'internal')
                    ->where('guarantor_customer_id', $member->id)
                    ->whereNull('loan_application_id')
                    ->whereIn('status', ['pending', 'accepted'])
                    ->latest('id')
                    ->first();
            }

            if ($invitation?->customer_guarantor_id) {
                $link = CustomerGuarantor::query()->find($invitation->customer_guarantor_id);
                if ($link?->guarantor_id) {
                    Guarantor::query()->where('id', $link->guarantor_id)->update([
                        'first_name' => $member->first_name,
                        'last_name' => $member->last_name,
                        'phone' => $member->phone ?? '',
                        'email' => $member->email,
                        'national_id' => $member->national_id,
                        'address' => $member->address,
                        'relationship' => 'member',
                    ]);
                }

                $invitation->update([
                    'guarantor_customer_id' => $member->id,
                    'membership_id' => MemberNumberFormatter::lookupKey($membershipId),
                    'invitee_name' => $member->full_name,
                    'requested_amount' => $requestedAmount,
                    'requested_tenure_months' => $requestedTenureMonths,
                    'loan_product_id' => $loanProductId,
                    'channel' => 'in_app',
                    'contact' => $member->phone ?? '',
                    'expires_at' => now()->addDays($this->invitationExpiryDays()),
                    'status' => ($link?->status === 'approved' || $invitation->status === 'accepted')
                        ? 'accepted'
                        : 'pending',
                ]);
                $this->syncInvitationQuote(
                    $invitation->fresh(),
                    $requestedAmount,
                    $requestedTenureMonths,
                    $loanProductId,
                );
                $link = $link ?? CustomerGuarantor::query()->find($invitation->customer_guarantor_id);
            } else {
                [$link, $invitation] = $this->createInternalInvitationRecords(
                    $borrower,
                    $member,
                    $membershipId,
                    $loanProductId,
                    $requestedAmount,
                    $requestedTenureMonths,
                    null,
                );
            }

            $this->ensureShortCode($invitation);

            // Nomination only. Invitation is dispatched after the borrower first gate passes.
            $shouldNotify = false;

            $status = $this->borrowerInvitationStatus($invitation->fresh(['customerGuarantor']));

            return [
                'invitation_id' => (int) $invitation->id,
                'customer_guarantor_id' => (int) ($link?->id ?? $invitation->customer_guarantor_id),
                'status' => (string) $invitation->status,
                'borrower_status_code' => $status['code'],
                'borrower_status_label' => $status['label'],
                'profile_percent' => $status['profile_percent'],
                'accepted' => $status['accepted'],
                'ready' => $status['ready'],
                'steps' => $status['steps'],
                'notified' => $shouldNotify,
                'invitee_name' => (string) ($invitation->invitee_name ?: $member->full_name),
            ];
        });
    }

    /** @return array{0: CustomerGuarantor, 1: GuarantorInvitation} */
    public function finalizeWizardInternalInvitation(
        Customer $borrower,
        LoanApplication $application,
        int $invitationId,
    ): array {
        $invitation = GuarantorInvitation::query()
            ->where('id', $invitationId)
            ->where('customer_id', $borrower->id)
            ->where('type', 'internal')
            ->whereNull('loan_application_id')
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if (! $invitation) {
            throw new \InvalidArgumentException('Internal guarantor invitation not found or already linked.');
        }

        $link = CustomerGuarantor::query()->find($invitation->customer_guarantor_id);
        if (! $link) {
            throw new \InvalidArgumentException('Internal guarantor link not found.');
        }

        $link->update(['loan_application_id' => $application->id]);
        $invitation->update([
            'loan_application_id' => $application->id,
            'loan_product_id' => $invitation->loan_product_id ?: $application->loan_product_id,
            'requested_amount' => $invitation->requested_amount ?: (int) $application->requested_amount,
            'requested_tenure_months' => $invitation->requested_tenure_months ?: (int) $application->requested_tenure_months,
        ]);

        $invitation = $this->syncInvitationQuote(
            $invitation->fresh(),
            (int) $application->requested_amount,
            (int) $application->requested_tenure_months,
            (int) $application->loan_product_id,
        );

        return [$link->fresh(), $invitation];
    }

    /** @return array{0: CustomerGuarantor, 1: GuarantorInvitation} */
    private function createInternalInvitationRecords(
        Customer $borrower,
        Customer $member,
        string $membershipId,
        ?int $loanProductId,
        ?int $requestedAmount,
        ?int $requestedTenureMonths,
        ?int $applicationId,
    ): array {
        $guarantor = Guarantor::create([
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'phone' => $member->phone ?? '',
            'email' => $member->email,
            'national_id' => $member->national_id,
            'address' => $member->address,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $guarantor->id,
            'loan_application_id' => $applicationId,
            'status' => 'pending',
        ]);

        $invitation = GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $applicationId,
            'loan_product_id' => $loanProductId,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => $member->id,
            'type' => 'internal',
            'channel' => 'in_app',
            'contact' => $member->phone ?? '',
            'membership_id' => MemberNumberFormatter::lookupKey($membershipId),
            'invitee_name' => $member->full_name,
            'requested_amount' => $requestedAmount,
            'requested_tenure_months' => $requestedTenureMonths,
            'token' => Str::random(48),
            'short_code' => $this->generateShortCode(),
            'status' => 'pending',
            'expires_at' => now()->addDays($this->invitationExpiryDays()),
        ]);

        return [$link, $invitation];
    }

    public function notifyInternalGuarantorRequest(
        Customer $borrower,
        Customer $member,
        CustomerGuarantor $link,
        GuarantorInvitation $invitation,
        ?LoanApplication $application,
    ): void {
        $product = $invitation->relationLoaded('product') && $invitation->product
            ? $invitation->product
            : ($invitation->loan_product_id
                ? LoanProduct::query()->find($invitation->loan_product_id)
                : null);
        $productName = $product?->localizedName();
        $reference = $application?->application_number
            ?? $application?->draft_reference
            ?? $productName
            ?? '—';
        $borrowerName = trim($borrower->first_name.' '.$borrower->last_name);

        // Avoid duplicate unread guarantor_request rows for the same link.
        $alreadyNotified = NotificationLog::query()
            ->where('customer_id', $member->id)
            ->where('template', 'guarantor_request')
            ->whereNull('read_at')
            ->where('recipient', 'like', '%/guarantor-requests/'.$link->id.'%')
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        app(NotificationService::class)->notifyInApp(
            $member,
            __('borrower.guarantor_invite.guarantor_received', [
                'borrower' => $borrowerName,
                'reference' => $reference,
            ]),
            'guarantor',
            'guarantor_request',
            __('borrower.guarantor_invite.notify_request_title'),
            route('site.borrower.guarantor-requests.show', $link),
            __('borrower.guarantor_notifications.view_request'),
            [
                'title_key' => 'borrower.guarantor_invite.notify_request_title',
                'body_key' => 'borrower.guarantor_invite.guarantor_received',
                'params' => [
                    'borrower' => $borrowerName,
                    'reference' => $reference,
                ],
                'customer_guarantor_id' => $link->id,
            ],
        );
    }

    public function attachExternal(
        Customer $borrower,
        LoanApplication $application,
        string $firstName,
        ?string $middleName,
        string $lastName,
        string $phone,
        ?string $email,
        string $relationship,
        string $region,
        string $district,
        string $channel,
    ): array {
        $phone = $this->normalizePhone($phone);
        $displayName = trim(collect([$firstName, $middleName, $lastName])->filter()->implode(' '));
        $address = trim(collect([$region, $district])->filter()->implode(', '));

        return DB::transaction(function () use ($borrower, $application, $firstName, $middleName, $lastName, $phone, $email, $relationship, $channel, $displayName, $address): array {
            $guarantor = Guarantor::create([
                'first_name' => trim($firstName.' '.($middleName ?: '')),
                'last_name' => $lastName,
                'phone' => $phone,
                'email' => $email,
                'relationship' => $relationship,
                'address' => $address,
            ]);

            $link = CustomerGuarantor::create([
                'customer_id' => $borrower->id,
                'guarantor_id' => $guarantor->id,
                'loan_application_id' => $application->id,
                'status' => 'pending',
            ]);

            $contact = $channel === 'email' ? ($email ?: $phone) : $phone;

            $invitation = GuarantorInvitation::create([
                'customer_id' => $borrower->id,
                'loan_application_id' => $application->id,
                'loan_product_id' => $application->loan_product_id,
                'customer_guarantor_id' => $link->id,
                'type' => 'external',
                'channel' => $channel,
                'contact' => $contact,
                'invitee_name' => $displayName,
                'requested_amount' => (int) $application->requested_amount,
                'requested_tenure_months' => (int) $application->requested_tenure_months,
                'token' => Str::random(48),
                'short_code' => $this->generateShortCode(),
                'status' => 'pending',
                'expires_at' => now()->addDays($this->invitationExpiryDays()),
            ]);

            // Nomination only. Invitation is dispatched after the borrower first gate passes.

            return [$link, $invitation];
        });
    }

    public function notifyBorrowerInvitationSent(Customer $borrower, GuarantorInvitation $invitation, string $inviteeName): void
    {
        $context = $this->invitationLoanContext($invitation);
        $message = __('borrower.guarantor_invite.borrower_sent', [
            'guarantor' => $inviteeName,
            'product' => $context['product_name'],
            'amount' => $context['amount_label'],
            'duration' => $context['duration_label'],
        ]);

        $actionUrl = $invitation->loan_application_id
            ? route('site.borrower.application', $invitation->loan_application_id)
            : route('site.borrower.loans', ['tab' => 'applications']);

        app(NotificationService::class)->notifyInApp(
            $borrower,
            $message,
            'guarantor',
            'guarantor_sent',
            __('borrower.guarantor_invite.notify_sent_title'),
            $actionUrl,
            __('borrower.notifications.view_application'),
            [
                'title_key' => 'borrower.guarantor_invite.notify_sent_title',
                'body_key' => 'borrower.guarantor_invite.borrower_sent',
                'params' => [
                    'guarantor' => $inviteeName,
                    'product' => $context['product_name'],
                    'amount' => $context['amount_label'],
                    'duration' => $context['duration_label'],
                ],
            ],
        );

        if (filled($borrower->phone)) {
            app(NotificationService::class)->sendSms(
                (string) $borrower->phone,
                $message,
                $borrower,
                'guarantor_sent',
            );
        }
    }

    /** In-app notice when a guarantor accepts the invitation. */
    public function notifyBorrowerAccepted(GuarantorInvitation $invitation): void
    {
        $invitation->loadMissing(['borrower', 'customerGuarantor.guarantor']);
        $borrower = $invitation->borrower;
        if (! $borrower) {
            return;
        }

        $guarantorName = trim((string) (
            $invitation->invitee_name
            ?: trim(($invitation->customerGuarantor?->guarantor?->first_name ?? '').' '.($invitation->customerGuarantor?->guarantor?->last_name ?? ''))
        ));
        if ($guarantorName === '') {
            $guarantorName = 'Guarantor';
        }

        $actionUrl = $invitation->loan_application_id
            ? route('site.borrower.application', $invitation->loan_application_id)
            : route('site.borrower.loans', ['tab' => 'applications']);

        try {
            app(NotificationService::class)->notifyInApp(
                $borrower,
                __('borrower.guarantor_invite.borrower_accepted', ['guarantor' => $guarantorName]),
                'guarantor',
                'guarantor_accepted',
                __('borrower.guarantor_invite.notify_accepted_title'),
                $actionUrl,
                __('borrower.notifications.view_application'),
                [
                    'title_key' => 'borrower.guarantor_invite.notify_accepted_title',
                    'body_key' => 'borrower.guarantor_invite.borrower_accepted',
                    'params' => ['guarantor' => $guarantorName],
                ],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function notifyExternalInvitation(Customer $borrower, GuarantorInvitation $invitation, string $inviteeName): void
    {
        $borrowerName = trim($borrower->first_name.' '.$borrower->last_name);
        $message = $this->invitationMessage($invitation);
        $invitation->loadMissing('customerGuarantor.guarantor');
        $email = trim((string) ($invitation->customerGuarantor?->guarantor?->email ?? ''));

        // Do not attach the borrower as the SMS/email log owner — that made the
        // guarantor-facing invite appear in the borrower's notification inbox.
        if ($invitation->channel === 'email' && $email !== '') {
            app(NotificationService::class)->sendEmail(
                $email,
                __('borrower.guarantor_invite.email_subject', ['borrower' => $borrowerName]),
                $message,
                null,
                'guarantor_invite',
            );
        } elseif ($invitation->contact) {
            app(NotificationService::class)->sendSms((string) $invitation->contact, $message, null, 'guarantor_invite');
        }
    }

    public function approve(CustomerGuarantor $link): void
    {
        DB::transaction(function () use ($link): void {
            $link->update(['status' => 'approved']);

            GuarantorInvitation::query()
                ->where('customer_guarantor_id', $link->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->get()
                ->each(function (GuarantorInvitation $invitation): void {
                    $invitation->update([
                        'status' => 'accepted',
                        'responded_at' => now(),
                    ]);
                    $this->recordConsentSnapshot($invitation->fresh());
                });

            $this->tryReleaseApplicationFromGuarantorHold($link->application ?? $link->fresh()->application);
        });

        app(NotificationCtaService::class)->consumeGuarantorRequestCtas($link);

        $invitation = GuarantorInvitation::query()
            ->where('customer_guarantor_id', $link->id)
            ->latest('id')
            ->first();
        if ($invitation) {
            $this->notifyBorrowerAccepted($invitation);
        }
    }

    /**
     * Null when an awaiting_guarantor application may enter Screening.
     * One answer for the list, the release, and UAT reconciliation.
     */
    public function guarantorHoldBlocker(?LoanApplication $application): ?string
    {
        if (! $application || $application->status !== 'awaiting_guarantor') {
            return 'not_on_guarantor_hold';
        }

        $approvedLinks = CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('status', 'approved')
            ->get();

        if ($approvedLinks->isEmpty()) {
            return 'no_approved_guarantor';
        }

        foreach ($approvedLinks as $approvedLink) {
            $invitation = GuarantorInvitation::query()
                ->where('customer_guarantor_id', $approvedLink->id)
                ->latest('id')
                ->first();
            if ($invitation?->needsQuoteReconfirmation()) {
                return 'guarantor_quote_reconfirmation_required';
            }
        }

        $access = app(GuarantorAccessService::class);
        $completion = app(ProfileCompletionService::class);
        $borrower = $application->customer;
        if (! $borrower || ! $completion->isFullyComplete($borrower)) {
            return 'borrower_profile_incomplete';
        }

        foreach ($approvedLinks as $approvedLink) {
            $guarantorCustomer = $access->guarantorCustomerForLink($approvedLink);
            if (! $guarantorCustomer) {
                return 'approved_guarantor_not_linked';
            }
            if (! $completion->isFullyComplete($guarantorCustomer)) {
                return 'guarantor_profile_incomplete';
            }
        }

        return null;
    }

    /**
     * Who still blocks entry to Screening, and the exact missing profile items.
     * Only while the application is still awaiting that entry. Later stages are left alone.
     *
     * @return array{applies: bool, parties: list<array{role: string, name: ?string, missing: list<string>, next: string, url: ?string}>}
     */
    public function preScreeningHold(?LoanApplication $application): array
    {
        if (! $application || $application->status !== 'awaiting_guarantor') {
            return ['applies' => false, 'parties' => []];
        }

        $completion = app(ProfileCompletionService::class);
        $parties = [];
        $borrower = $application->customer;
        if ($borrower && ! $completion->isFullyComplete($borrower)) {
            $parties[] = $this->incompleteProfileParty('borrower', $borrower, $completion);
        }

        $approvedLinks = CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('status', 'approved')
            ->get();

        if ($approvedLinks->isEmpty()) {
            $parties[] = [
                'role' => 'guarantor',
                'name' => null,
                'missing' => [__('borrower.loan_profile.prescreening_guarantor_not_ready')],
                'next' => __('borrower.loan_profile.prescreening_guarantor_must_accept'),
                'url' => null,
            ];
        } else {
            $access = app(GuarantorAccessService::class);
            foreach ($approvedLinks as $approvedLink) {
                $guarantorCustomer = $access->guarantorCustomerForLink($approvedLink);
                if (! $guarantorCustomer) {
                    $parties[] = [
                        'role' => 'guarantor',
                        'name' => $approvedLink->displayName(),
                        'missing' => [__('borrower.loan_profile.prescreening_guarantor_not_linked')],
                        'next' => __('borrower.loan_profile.prescreening_guarantor_must_accept'),
                        'url' => null,
                    ];

                    continue;
                }
                if (! $completion->isFullyComplete($guarantorCustomer)) {
                    $parties[] = $this->incompleteProfileParty('guarantor', $guarantorCustomer, $completion);
                }
            }
        }

        return ['applies' => true, 'parties' => $parties];
    }

    /**
     * @return array{role: string, name: ?string, missing: list<string>, next: string, url: ?string}
     */
    private function incompleteProfileParty(string $role, Customer $customer, ProfileCompletionService $completion): array
    {
        $summary = $completion->completionSummary($customer);
        $missing = collect($summary['actionable'] ?? [])
            ->pluck('label')
            ->filter(fn ($label) => filled($label))
            ->values()
            ->all();
        $name = trim((string) ($customer->legalDisplayName() ?? ''));
        if ($name === '') {
            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        }
        $nextUrl = $role === 'borrower'
            ? (collect($summary['actionable'] ?? [])->first()['url'] ?? route('site.borrower.profile'))
            : null;

        return [
            'role' => $role,
            'name' => $name !== '' ? $name : null,
            'missing' => $missing !== [] ? $missing : [__('borrower.loan_profile.prescreening_profile_incomplete')],
            'next' => $role === 'borrower'
                ? __('borrower.loan_profile.prescreening_borrower_next')
                : __('borrower.loan_profile.prescreening_guarantor_next', ['name' => $name !== '' ? $name : __('borrower.application.guarantor_role')]),
            'url' => $nextUrl,
        ];
    }

    /**
     * Move awaiting_guarantor → ready_for_screening when the borrower and every approved guarantor
     * are complete. Staff then send the file to Credit Screening.
     */
    public function tryReleaseApplicationFromGuarantorHold(?LoanApplication $application): bool
    {
        if ($this->guarantorHoldBlocker($application) !== null) {
            return false;
        }

        return app(ApplicationIntakeTransitionService::class)->releaseToReadyForScreening($application->fresh());
    }

    /**
     * One stage-history and audit row for the automatic pre-Screening release.
     * A later profile save must not write a second Screening entry.
     */
    private function recordAutomaticScreeningEntry(LoanApplication $application, string $fromStage, string $fromStatus): void
    {
        $alreadyRecorded = ApplicationStageHistory::query()
            ->where('loan_application_id', $application->id)
            ->where('to_stage', 'screening')
            ->where('remarks', 'like', 'Entered Credit Screening%')
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $remarks = 'Entered Credit Screening. Actor: System (automatic). '
            .'Reason: pre-Screening requirements completed. '
            .'Status: '.$fromStatus.' → submitted.';

        ApplicationStageHistory::create([
            'loan_application_id' => $application->id,
            'from_stage' => $fromStage,
            'to_stage' => 'screening',
            'changed_by' => null,
            'remarks' => $remarks,
        ]);

        app(AuditService::class)->log(null, 'application.stage_changed', $application, [
            'current_stage' => $fromStage,
            'status' => $fromStatus,
        ], [
            'current_stage' => 'screening',
            'status' => 'submitted',
            'actor' => 'system',
            'action' => 'entered_credit_screening',
            'reason' => 'pre-Screening requirements completed',
            'remarks' => $remarks,
        ]);
    }

    /** After a guarantor finishes their profile, release any held applications they already accepted. */
    public function releaseHeldApplicationsForGuarantor(Customer $guarantor): int
    {
        if (! app(ProfileCompletionService::class)->isFullyComplete($guarantor)) {
            return 0;
        }

        $released = 0;
        $linkIds = GuarantorInvitation::query()
            ->where('guarantor_customer_id', $guarantor->id)
            ->whereNotNull('customer_guarantor_id')
            ->pluck('customer_guarantor_id');

        CustomerGuarantor::query()
            ->with('application')
            ->whereIn('id', $linkIds)
            ->where('status', 'approved')
            ->get()
            ->each(function (CustomerGuarantor $link) use (&$released): void {
                if ($this->tryReleaseApplicationFromGuarantorHold($link->application)) {
                    $released++;
                }
            });

        return $released;
    }

    /** When the borrower finishes their own profile, release their held applications too. */
    public function releaseHeldApplicationsForBorrower(Customer $borrower): int
    {
        if (! app(ProfileCompletionService::class)->isFullyComplete($borrower)) {
            return 0;
        }

        $released = 0;
        LoanApplication::query()
            ->where('customer_id', $borrower->id)
            ->where('status', 'awaiting_guarantor')
            ->orderBy('id')
            ->each(function (LoanApplication $application) use (&$released): void {
                if ($this->tryReleaseApplicationFromGuarantorHold($application)) {
                    $released++;
                }
            });

        return $released;
    }

    public function releaseHeldApplicationsForMember(Customer $customer): int
    {
        if (! app(ProfileCompletionService::class)->isFullyComplete($customer)) {
            return 0;
        }

        return $this->releaseHeldApplicationsForGuarantor($customer)
            + $this->releaseHeldApplicationsForBorrower($customer);
    }

    /**
     * Public / authenticated decline entry — always flips invitation + linked CustomerGuarantor.
     */
    public function rejectInvitation(GuarantorInvitation $invitation, ?string $notes = null): void
    {
        $invitation->loadMissing(['customerGuarantor.guarantor', 'customerGuarantor.customer', 'borrower']);

        DB::transaction(function () use ($invitation, $notes): void {
            $link = $invitation->customerGuarantor;
            if ($link && in_array((string) $link->status, ['pending', 'approved'], true)) {
                $link->update(['status' => 'rejected']);
            }

            // Reject this invitation and any open twin on the same link — never leave half-pending.
            GuarantorInvitation::query()
                ->where(function ($q) use ($invitation) {
                    $q->whereKey($invitation->id);
                    if ($invitation->customer_guarantor_id) {
                        $q->orWhere('customer_guarantor_id', $invitation->customer_guarantor_id);
                    }
                })
                ->whereIn('status', ['pending', 'accepted'])
                ->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                    'response_notes' => $notes,
                    'confirmation_status' => null,
                ]);
        });

        $invitation->refresh();
        $link = $invitation->customerGuarantor
            ?? ($invitation->customer_guarantor_id
                ? CustomerGuarantor::query()->find($invitation->customer_guarantor_id)
                : null);

        try {
            $borrower = $link?->customer ?? $invitation->borrower;
            $guarantorName = trim((string) (
                $invitation->invitee_name
                ?: trim(($link?->guarantor?->first_name ?? '').' '.($link?->guarantor?->last_name ?? ''))
            ));
            if ($borrower && $guarantorName !== '') {
                $applicationId = $link?->loan_application_id ?? $invitation->loan_application_id;
                $actionUrl = $applicationId
                    ? route('site.borrower.application', $applicationId)
                    : route('site.borrower.loans', ['tab' => 'applications']);

                app(NotificationService::class)->notifyInApp(
                    $borrower,
                    __('borrower.guarantor_invite.borrower_declined', ['guarantor' => $guarantorName]),
                    'guarantor',
                    'guarantor_declined',
                    __('borrower.guarantor_invite.notify_declined_title'),
                    $actionUrl,
                    __('borrower.notifications.view_application'),
                    [
                        'title_key' => 'borrower.guarantor_invite.notify_declined_title',
                        'body_key' => 'borrower.guarantor_invite.borrower_declined',
                        'params' => ['guarantor' => $guarantorName],
                    ],
                );
            }
            if ($link) {
                app(NotificationCtaService::class)->consumeGuarantorRequestCtas($link);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function reject(CustomerGuarantor $link, ?string $notes = null): void
    {
        $link->loadMissing(['invitation', 'guarantor', 'customer']);
        $invitation = GuarantorInvitation::query()
            ->where('customer_guarantor_id', $link->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->latest('id')
            ->first()
            ?? $link->invitation;

        if ($invitation) {
            $this->rejectInvitation($invitation, $notes);

            return;
        }

        DB::transaction(function () use ($link, $notes): void {
            $link->update(['status' => 'rejected']);
            GuarantorInvitation::query()
                ->where('customer_guarantor_id', $link->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                    'response_notes' => $notes,
                    'confirmation_status' => null,
                ]);
        });

        try {
            app(NotificationCtaService::class)->consumeGuarantorRequestCtas($link);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Decline this guarantor for this application only (underwriting).
     * Does not notify the borrower — caller sends the "change guarantor" message.
     * Does not affect the guarantor's own membership or credit file.
     */
    public function rejectByUnderwriting(CustomerGuarantor $link, ?string $notes = null): void
    {
        DB::transaction(function () use ($link, $notes): void {
            $link->update(['status' => 'rejected']);

            GuarantorInvitation::query()
                ->where('customer_guarantor_id', $link->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                    'response_notes' => $notes ?: 'Declined by underwriting for this application',
                ]);
        });
    }

    /**
     * Current external invitation shown on Application View (latest non-history).
     */
    public function currentExternalInvitationForApplication(LoanApplication $application): ?GuarantorInvitation
    {
        return GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->where('type', 'external')
            ->whereNotIn('status', ['rejected', 'declined', 'expired', 'cancelled', 'replaced'])
            ->with(['customerGuarantor.guarantor'])
            ->latest('id')
            ->first();
    }

    /**
     * Invitation the borrower may still edit (before acceptance/consent only).
     */
    public function currentBorrowerEditableExternalInvitation(LoanApplication $application): ?GuarantorInvitation
    {
        return GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->where('type', 'external')
            ->whereIn('status', ['pending', 'opened', 'sent'])
            ->with(['customerGuarantor.guarantor'])
            ->latest('id')
            ->first();
    }

    /**
     * Form seed for Hariri mdhamini — same identity Application View displays (invitee_name + contact).
     *
     * @return array{
     *     external_first_name: string,
     *     external_middle_name: string,
     *     external_last_name: string,
     *     external_phone: string,
     *     external_email: string,
     *     external_relationship: string,
     *     external_region: string,
     *     external_district: string,
     *     external_invitation_id: int,
     *     invitee_name: string
     * }
     */
    public function borrowerEditFieldSeed(GuarantorInvitation $invitation): array
    {
        $g = $invitation->customerGuarantor?->guarantor;
        $displayName = trim((string) ($invitation->invitee_name ?? ''));
        if ($displayName === '') {
            $displayName = trim(collect([$g?->first_name, $g?->last_name])->filter()->implode(' '));
        }

        $parts = preg_split('/\s+/', $displayName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = (string) ($parts[0] ?? '');
        $middle = '';
        $last = '';
        if (count($parts) === 2) {
            $last = (string) $parts[1];
        } elseif (count($parts) >= 3) {
            $last = (string) array_pop($parts);
            array_shift($parts);
            $middle = trim(implode(' ', $parts));
        }

        $phone = (string) ($invitation->contact ?: ($g?->phone ?? ''));
        $phoneDigits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($phoneDigits, '255') && strlen($phoneDigits) >= 12) {
            $phoneDigits = substr($phoneDigits, 3);
        } elseif (str_starts_with($phoneDigits, '0')) {
            $phoneDigits = substr($phoneDigits, 1);
        }

        $region = '';
        $district = '';
        $address = trim((string) ($g?->address ?? ''));
        if ($address !== '' && str_contains($address, ',')) {
            [$region, $district] = array_map('trim', explode(',', $address, 2));
        }

        return [
            'external_first_name' => $first,
            'external_middle_name' => $middle,
            'external_last_name' => $last,
            'external_phone' => $phoneDigits,
            'external_email' => (string) ($g?->email ?? ''),
            'external_relationship' => (string) ($g?->relationship ?? ''),
            'external_region' => $region,
            'external_district' => $district,
            'external_invitation_id' => (int) $invitation->id,
            'invitee_name' => $displayName,
        ];
    }

    /**
     * Edit the current guarantor nomination in place (same person).
     * Regenerates the invitation token when contact identity changes so the old link dies.
     * Borrower edits are allowed only before acceptance (pending/opened/sent).
     *
     * @return array{invitation_id: int, invitation_url: string, short_url: string, whatsapp_url: string|null, sms_url: string|null, email_url: string|null, status: string, borrower_status_code: string, borrower_status_label: string, profile_percent: int|null, accepted: bool, ready: bool, steps: list<array{key: string, label: string, complete: bool, current: bool}>}
     */
    public function updateCurrentExternalInvitationDetails(
        Customer $borrower,
        LoanApplication $application,
        int $invitationId,
        string $firstName,
        ?string $middleName,
        string $lastName,
        string $phone,
        ?string $email,
        ?string $relationship = null,
        ?string $region = null,
        ?string $district = null,
    ): array {
        if ((int) $application->customer_id !== (int) $borrower->id) {
            throw new \InvalidArgumentException('Application does not belong to this borrower.');
        }

        // Consent lock: after accept, borrower cannot change identity/contact.
        $invitation = GuarantorInvitation::query()
            ->where('id', $invitationId)
            ->where('customer_id', $borrower->id)
            ->where('loan_application_id', $application->id)
            ->where('type', 'external')
            ->whereIn('status', ['pending', 'opened', 'sent'])
            ->first();

        if (! $invitation) {
            throw new \InvalidArgumentException(__('borrower.guarantor_invite.no_longer_active'));
        }

        $current = $this->currentBorrowerEditableExternalInvitation($application);
        if (! $current || (int) $current->id !== (int) $invitation->id) {
            throw new \InvalidArgumentException(__('borrower.guarantor_invite.no_longer_active'));
        }

        $phone = $this->normalizePhone($phone);
        if ($member = $this->findMemberCustomerByPhone($phone)) {
            throw new \InvalidArgumentException(__('borrower.apply.alerts.guarantor_phone_is_member', [
                'name' => trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
            ]));
        }

        $displayName = trim(collect([$firstName, $middleName, $lastName])->filter()->implode(' '));
        $identityChanged = $invitation->contact !== $phone
            || $invitation->invitee_name !== $displayName;
        $address = trim(collect([$region, $district])->filter()->implode(', '));

        return DB::transaction(function () use (
            $invitation,
            $borrower,
            $application,
            $firstName,
            $middleName,
            $lastName,
            $phone,
            $email,
            $relationship,
            $displayName,
            $identityChanged,
            $address,
        ): array {
            $link = CustomerGuarantor::query()->find($invitation->customer_guarantor_id);
            if ($link?->guarantor_id) {
                $guarantorUpdates = [
                    'first_name' => trim($firstName.($middleName ? ' '.$middleName : '')),
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'email' => $email,
                ];
                if ($relationship !== null && $relationship !== '') {
                    $guarantorUpdates['relationship'] = $relationship;
                }
                if ($address !== '') {
                    $guarantorUpdates['address'] = $address;
                }
                Guarantor::query()->where('id', $link->guarantor_id)->update($guarantorUpdates);
            }

            $updates = [
                'contact' => $phone,
                'invitee_name' => $displayName,
                'requested_amount' => (int) $application->requested_amount,
                'requested_tenure_months' => (int) $application->requested_tenure_months,
                'loan_product_id' => $application->loan_product_id,
                'expires_at' => now()->addDays($this->invitationExpiryDays()),
                'status' => 'pending',
                'responded_at' => null,
                'response_notes' => null,
                'confirmation_status' => null,
            ];
            if ($identityChanged) {
                // Invalidate the previous share link — same nomination, new token.
                $updates['token'] = Str::random(48);
                $updates['short_code'] = $this->generateShortCode();
            }

            $invitation->update($updates);
            $this->ensureShortCode($invitation->fresh());

            return $this->sharePayload(
                $invitation->fresh(['application.product', 'product', 'borrower', 'customerGuarantor.guarantor']),
                $borrower,
            );
        });
    }

    public function hasApprovedGuarantor(LoanApplication $application): bool
    {
        return CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('status', 'approved')
            ->exists();
    }

    /**
     * True when at least one approved guarantor has a complete profile (same bar as release from hold).
     */
    public function hasReadyGuarantor(LoanApplication $application): bool
    {
        $approvedLinks = CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('status', 'approved')
            ->get();

        if ($approvedLinks->isEmpty()) {
            return false;
        }

        $onboarding = app(GuarantorOnboardingService::class);
        $access = app(GuarantorAccessService::class);

        foreach ($approvedLinks as $approvedLink) {
            $invitation = GuarantorInvitation::query()
                ->where('customer_guarantor_id', $approvedLink->id)
                ->latest('id')
                ->first();
            if ($invitation?->needsQuoteReconfirmation()) {
                return false;
            }
            $guarantorCustomer = $access->guarantorCustomerForLink($approvedLink);
            if (! $guarantorCustomer || ! ($onboarding->guarantorProfileStatus($guarantorCustomer)['met'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * When the borrower changes the accepted quote on a draft, push it to open invitations
     * and supersede prior guarantor consent when the change is material.
     */
    public function syncOpenInvitationQuotesFromDraft(Customer $borrower, int $loanProductId, array $form, ?string $draftReference = null): void
    {
        $amount = isset($form['requested_amount']) ? (int) $form['requested_amount'] : null;
        $tenure = isset($form['requested_tenure_months']) ? (int) $form['requested_tenure_months'] : null;
        if (($amount === null || $amount <= 0) && ($tenure === null || $tenure <= 0)) {
            return;
        }

        GuarantorInvitation::query()
            ->where('customer_id', $borrower->id)
            ->where('loan_product_id', $loanProductId)
            ->whereNull('loan_application_id')
            ->whereIn('status', ['pending', 'accepted'])
            ->each(function (GuarantorInvitation $invitation) use ($amount, $tenure, $loanProductId): void {
                $this->syncInvitationQuote(
                    $invitation,
                    $amount && $amount > 0 ? $amount : null,
                    $tenure && $tenure > 0 ? $tenure : null,
                    $loanProductId,
                );
            });
    }

    protected function ensureShortCode(GuarantorInvitation $invitation): string
    {
        if ($invitation->short_code) {
            return $invitation->short_code;
        }

        $code = $this->generateShortCode();
        $invitation->update(['short_code' => $code]);

        return $code;
    }

    protected function generateShortCode(): string
    {
        do {
            $code = strtoupper(Str::random(3)).random_int(100, 999);
        } while (GuarantorInvitation::query()->where('short_code', $code)->exists());

        return $code;
    }

    protected function namesMatch(string $input, Customer $member): bool
    {
        $inputNorm = $this->normalizePersonName($input);
        if ($inputNorm === '') {
            return false;
        }

        $canonical = $this->normalizePersonName(trim(($member->first_name ?? '').' '.($member->last_name ?? '')));
        if ($inputNorm === $canonical) {
            return true;
        }

        $reverse = $this->normalizePersonName(trim(($member->last_name ?? '').' '.($member->first_name ?? '')));

        return $inputNorm === $reverse;
    }

    protected function normalizePersonName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? '';

        return $name;
    }

    protected function invitationExpiryDays(): int
    {
        return app(UnderwritingSettingsService::class)->guarantorInvitationExpiryDays();
    }

    /**
     * @return list<array{id: int, label: string, mode: string, membership_id: ?string, phone: string, name: string, kyc_fresh: bool}>
     */
    public function previousGuarantorsForBorrower(Customer $borrower): array
    {
        $links = CustomerGuarantor::query()
            ->with(['guarantor', 'loanApplication'])
            ->where('customer_id', $borrower->id)
            ->whereNotNull('guarantor_id')
            ->latest('id')
            ->get();

        $seen = [];
        $items = [];

        foreach ($links as $link) {
            $guarantor = $link->guarantor;
            if (! $guarantor) {
                continue;
            }

            $invitation = GuarantorInvitation::query()
                ->where('customer_guarantor_id', $link->id)
                ->latest('id')
                ->first();

            $member = $invitation?->guarantor_customer_id
                ? Customer::find($invitation->guarantor_customer_id)
                : null;

            $dedupeKeys = ['g:'.$guarantor->id];
            $phoneKey = $this->normalizePhone($guarantor->phone ?: ($member?->phone ?? ''));
            if ($phoneKey !== '') {
                $dedupeKeys[] = 'p:'.$phoneKey;
            }
            $nid = strtolower(trim((string) ($guarantor->national_id ?? '')));
            if ($nid !== '') {
                $dedupeKeys[] = 'n:'.$nid;
            }
            $memberNo = strtolower(trim((string) ($invitation?->membership_id ?: $member?->member_no ?: '')));
            if ($memberNo !== '') {
                $dedupeKeys[] = 'm:'.$memberNo;
            }
            $email = strtolower(trim((string) ($guarantor->email ?? '')));
            if ($email !== '') {
                $dedupeKeys[] = 'e:'.$email;
            }

            $alreadySeen = false;
            foreach ($dedupeKeys as $key) {
                if (isset($seen[$key])) {
                    $alreadySeen = true;
                    break;
                }
            }
            if ($alreadySeen) {
                continue;
            }
            foreach ($dedupeKeys as $key) {
                $seen[$key] = true;
            }

            $mode = $invitation?->type === 'internal' || $member ? 'internal' : 'external';
            $label = trim(($guarantor->first_name ?? '').' '.($guarantor->last_name ?? '')) ?: 'Guarantor';
            if ($member) {
                $memberLabel = trim(($member->first_name ?? '').' '.($member->last_name ?? ''));
                if ($memberLabel !== '') {
                    $label = $memberLabel;
                }
            }
            $kycFresh = $member
                ? (bool) (collect(app(ApplicationRequirementsService::class)->checklist($member)['items'] ?? [])
                    ->firstWhere('key', 'kyc_freshness')['complete'] ?? false)
                : false;

            $items[] = [
                'id' => $link->id,
                'label' => $label,
                'mode' => $mode,
                'membership_id' => $invitation?->membership_id,
                'phone' => $guarantor->phone ?: ($member?->phone ?? ''),
                'name' => $label,
                'kyc_fresh' => $kycFresh,
            ];
        }

        return $items;
    }

    /**
     * @return array{ok: bool, message: string, lookup?: array<string, mixed>}
     */
    public function prepareWizardPreviousGuarantor(Customer $borrower, int $customerGuarantorId): array
    {
        $link = CustomerGuarantor::query()
            ->with(['guarantor'])
            ->where('customer_id', $borrower->id)
            ->where('id', $customerGuarantorId)
            ->first();

        if (! $link || ! $link->guarantor) {
            return ['ok' => false, 'message' => __('borrower.apply.alerts.guarantor_not_found')];
        }

        $invitation = GuarantorInvitation::query()
            ->where('customer_guarantor_id', $link->id)
            ->latest('id')
            ->first();

        if ($invitation?->type === 'internal' && $invitation->membership_id) {
            $member = $this->findCustomerByMemberNumber($invitation->membership_id);
            if ($member) {
                $verified = $this->verifyInternalMember(
                    $borrower,
                    $invitation->membership_id,
                    $member->phone ?? $link->guarantor->phone ?? '',
                    trim(($link->guarantor->first_name ?? '').' '.($link->guarantor->last_name ?? '')),
                );

                if ($verified['ok']) {
                    return [
                        'ok' => true,
                        'message' => __('borrower.apply.previous_guarantor.ready'),
                        'lookup' => [
                            'ok' => true,
                            'name' => $verified['name'] ?? $verified['label'] ?? $link->guarantor->first_name,
                            'label' => $verified['label'] ?? null,
                            'member_no' => $invitation->membership_id,
                            'previous_guarantor_id' => $link->id,
                            'kyc_fresh' => true,
                        ],
                    ];
                }
            }
        }

        return [
            'ok' => true,
            'message' => __('borrower.apply.previous_guarantor.request_sent'),
            'lookup' => [
                'ok' => true,
                'name' => trim(($link->guarantor->first_name ?? '').' '.($link->guarantor->last_name ?? '')),
                'label' => trim(($link->guarantor->first_name ?? '').' '.($link->guarantor->last_name ?? '')),
                'member_no' => $invitation?->membership_id,
                'previous_guarantor_id' => $link->id,
                'kyc_fresh' => false,
            ],
        ];
    }
}
