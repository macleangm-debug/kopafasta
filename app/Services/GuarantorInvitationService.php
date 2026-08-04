<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
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

        if (! $customer || ! $customer->hasMembership()) {
            return null;
        }

        return $customer;
    }

    public function isEligibleInternalGuarantor(Customer $customer): bool
    {
        return $customer->hasMembership()
            && ($customer->isMembershipActive() || $customer->isMembershipInGrace());
    }

    /**
     * @return array{ok: bool, message: string, name?: string, label?: string, member?: Customer}
     */
    public function verifyInternalMember(Customer $borrower, string $membershipId, string $phone, string $name): array
    {
        $member = $this->findCustomerByMemberNumber($membershipId);
        if (! $member) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.alerts.guarantor_not_found'),
            ];
        }

        if (! $member->hasMembership()) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.alerts.guarantor_not_member'),
            ];
        }

        if (! $member->isMembershipActive() && ! $member->isMembershipInGrace()) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.alerts.guarantor_membership_inactive'),
            ];
        }

        if ($member->id === $borrower->id) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.alerts.guarantor_self'),
            ];
        }

        $inputPhone = $this->normalizePhone($phone);
        $memberPhone = $this->normalizePhone($member->phone);
        if ($inputPhone === '' || $memberPhone === '' || $inputPhone !== $memberPhone) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.alerts.guarantor_phone_mismatch'),
            ];
        }

        // Name is optional on the wizard form — membership + phone identify the member.
        // When a name is supplied (e.g. previous-guarantor re-verify), still check it.
        if (trim($name) !== '' && ! $this->namesMatch($name, $member)) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.alerts.guarantor_name_mismatch'),
            ];
        }

        $displayName = trim(($member->first_name ?? '').' '.($member->last_name ?? ''));
        $statusKey = $member->isMembershipActive()
            ? 'active'
            : ($member->isMembershipInGrace() ? 'grace' : 'inactive');

        return [
            'ok'      => true,
            'message' => __('borrower.apply.alerts.guarantor_verified'),
            'name'    => $displayName,
            'label'   => trim($displayName.' · '.__('borrower.apply.guarantor_fields.membership_'.$statusKey)),
            'member'  => $member,
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
            $borrowerName = trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? ''));

            return __('borrower.guarantor_invite.message', [
                'guarantor_name' => $guarantorName,
                'borrower_name'  => $borrowerName,
                'product'        => $context['product_name'],
                'amount'         => $context['amount_label'],
                'duration'       => $context['duration_label'],
                'link'           => $url,
            ]);
        } finally {
            app()->setLocale($previous);
        }
    }

    /** @return array{amount: int, amount_label: string, tenure_months: int, duration_label: string, product_name: string, installment_label: string} */
    public function invitationLoanContext(GuarantorInvitation $invitation): array
    {
        $invitation->loadMissing(['application.product', 'product']);
        $product = $this->resolveInvitationProduct($invitation);
        $amount = (int) ($invitation->application?->requested_amount ?? $invitation->requested_amount ?? 0);
        $tenure = (int) ($invitation->application?->requested_tenure_months ?? $invitation->requested_tenure_months ?? 0);
        $productName = trim((string) ($product?->localizedName() ?? ''));
        $installmentLabel = __('borrower.guarantor_invite.installment_tbd');

        if ($amount > 0 && $tenure > 0 && $product) {
            $monthlyRate = app(DisplayedRateService::class)->displayedMonthlyRate($product, (float) $amount);
            $cadence = $product->repayment_cadence ?? 'weekly';
            $preview = app(RepaymentScheduleGenerator::class)->preview($amount, $monthlyRate, $tenure, $cadence);
            $first = $preview[0] ?? null;
            if ($first) {
                $installmentLabel = 'TZS '.number_format((float) $first['total_due']);
            }
        }

        return [
            'amount'             => $amount,
            'amount_label'       => $amount > 0 ? 'TZS '.number_format($amount) : __('borrower.guarantor_invite.amount_tbd'),
            'tenure_months'      => $tenure,
            'duration_label'     => $tenure > 0
                ? trans_choice('borrower.guarantor_invite.duration_months', $tenure, ['count' => $tenure])
                : __('borrower.guarantor_invite.duration_tbd'),
            'product_name'       => $productName !== '' ? $productName : __('borrower.guarantor_invite.product_tbd'),
            'installment_label'  => $installmentLabel,
        ];
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

            if (! $guarantorCustomer->hasMembership()) {
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
        $labelKey = match ($code) {
            'pending_acceptance' => 'pending_acceptance',
            'pending_profile' => 'pending_profile',
            'ready' => 'ready',
            'guarantee_pending' => 'guarantee_pending',
            'registration_in_progress' => 'registration_in_progress',
            'kyc_in_progress' => 'kyc_in_progress',
            'rejected' => 'rejected',
            'expired' => 'expired',
            'accepted' => 'accepted',
            default => 'invitation_sent',
        };

        $invitedDone = true;
        $acceptedDone = $accepted || $ready || in_array($code, ['pending_profile', 'guarantee_pending', 'ready'], true);
        $profileDone = $ready || ($acceptedDone && $profilePercent !== null && $profilePercent >= 100 && $code === 'ready');
        if ($code === 'ready') {
            $profileDone = true;
        }
        if ($code === 'pending_profile') {
            $profileDone = false;
        }

        $current = match ($code) {
            'pending_acceptance', 'invitation_sent', 'registration_in_progress', 'kyc_in_progress' => 'accepted',
            'pending_profile', 'guarantee_pending' => 'profile',
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
                'key'      => 'invited',
                'label'    => __('borrower.apply.guarantor_progress.invited'),
                'complete' => $invitedDone,
                'current'  => false,
            ],
            [
                'key'      => 'accepted',
                'label'    => __('borrower.apply.guarantor_progress.accepted'),
                'complete' => $acceptedDone,
                'current'  => $current === 'accepted',
            ],
            [
                'key'      => 'profile',
                'label'    => $profilePercent !== null
                    ? __('borrower.apply.guarantor_progress.profile_pct', ['percent' => $profilePercent])
                    : __('borrower.apply.guarantor_progress.profile'),
                'complete' => $profileDone || $ready,
                'current'  => $current === 'profile',
            ],
            [
                'key'      => 'ready',
                'label'    => __('borrower.apply.guarantor_progress.ready'),
                'complete' => $ready,
                'current'  => $current === 'ready',
            ],
        ];

        return [
            'code'             => $code,
            'label'            => __('borrower.apply.guarantor_status.'.$labelKey),
            'profile_percent'  => $profilePercent,
            'accepted'         => $acceptedDone,
            'ready'            => $ready,
            'steps'            => $steps,
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
            'invitation_id'          => $invitation->id,
            'invitation_url'         => $this->invitationUrl($invitation),
            'short_url'              => $this->shortInvitationUrl($invitation),
            'whatsapp_url'           => $this->whatsAppShareUrl($invitation, $borrower),
            'sms_url'                => $this->smsShareUrl($invitation),
            'email_url'              => $this->emailShareUrl($invitation),
            'status'                 => (string) ($invitation->status ?? 'pending'),
            'borrower_status_code'   => $borrowerStatus['code'],
            'borrower_status_label'  => $borrowerStatus['label'],
            'profile_percent'        => $borrowerStatus['profile_percent'],
            'accepted'               => $borrowerStatus['accepted'],
            'ready'                  => $borrowerStatus['ready'],
            'steps'                  => $borrowerStatus['steps'],
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
    ): array {
        $phone = $this->normalizePhone($phone);
        if ($member = $this->findMemberCustomerByPhone($phone)) {
            throw new \InvalidArgumentException(__('borrower.apply.alerts.guarantor_phone_is_member', [
                'name' => trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
            ]));
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
            $region,
            $district,
            $channel,
            $contact,
            $displayName,
            $address,
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
                    ->where('type', 'external')
                    ->whereNull('loan_application_id')
                    ->whereIn('status', ['pending', 'accepted'])
                    ->first();
            }

            if ($invitation?->customer_guarantor_id) {
                $link = CustomerGuarantor::query()->find($invitation->customer_guarantor_id);
                if ($link?->guarantor_id) {
                    Guarantor::query()->where('id', $link->guarantor_id)->update([
                        'first_name'   => trim($firstName.' '.($middleName ?: '')),
                        'last_name'    => $lastName,
                        'phone'        => $phone,
                        'email'        => $email,
                        'relationship' => $relationship,
                        'address'      => $address,
                    ]);
                }
                $identityChanged = $invitation->contact !== $contact
                    || $invitation->invitee_name !== $displayName;

                $updates = [
                    'channel'                 => $channel,
                    'contact'                 => $contact,
                    'invitee_name'            => $displayName,
                    'requested_amount'        => $requestedAmount,
                    'requested_tenure_months' => $requestedTenureMonths,
                    'loan_product_id'         => $loanProductId,
                    'expires_at'              => now()->addDays($this->invitationExpiryDays()),
                    'status'                  => 'pending',
                ];

                if ($identityChanged) {
                    $updates['token'] = Str::random(48);
                    $updates['short_code'] = $this->generateShortCode();
                }

                $invitation->update($updates);
            } else {
                $guarantor = Guarantor::create([
                    'first_name'   => trim($firstName.' '.($middleName ?: '')),
                    'last_name'    => $lastName,
                    'phone'        => $phone,
                    'email'        => $email,
                    'relationship' => $relationship,
                    'address'      => $address,
                ]);

                $link = CustomerGuarantor::create([
                    'customer_id'         => $borrower->id,
                    'guarantor_id'        => $guarantor->id,
                    'loan_application_id' => null,
                    'status'              => 'pending',
                ]);

                $invitation = GuarantorInvitation::create([
                    'customer_id'             => $borrower->id,
                    'loan_application_id'     => null,
                    'loan_product_id'         => $loanProductId,
                    'customer_guarantor_id'     => $link->id,
                    'type'                    => 'external',
                    'channel'                 => $channel,
                    'contact'                 => $contact,
                    'invitee_name'            => $displayName,
                    'requested_amount'        => $requestedAmount,
                    'requested_tenure_months' => $requestedTenureMonths,
                    'token'                   => Str::random(48),
                    'short_code'              => $this->generateShortCode(),
                    'status'                  => 'pending',
                    'expires_at'              => now()->addDays($this->invitationExpiryDays()),
                ]);
            }

            $this->ensureShortCode($invitation);

            $invitationId = (int) $invitation->id;
            $borrowerId = (int) $borrower->id;
            $sentInviteeName = $displayName;

            DB::afterCommit(function () use ($invitationId, $borrowerId, $sentInviteeName): void {
                $freshInvitation = GuarantorInvitation::query()->find($invitationId);
                $freshBorrower = Customer::query()->find($borrowerId);
                if (! $freshInvitation || ! $freshBorrower) {
                    return;
                }

                try {
                    $this->notifyExternalInvitation($freshBorrower, $freshInvitation, $sentInviteeName);
                    $this->notifyBorrowerInvitationSent($freshBorrower, $freshInvitation, $sentInviteeName);
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            return $this->sharePayload($invitation, $borrower);
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
            ->whereNull('loan_application_id')
            ->whereIn('status', ['pending', 'accepted'])
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
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $invitation->loan_product_id ?: $application->loan_product_id,
            'requested_amount'        => $invitation->requested_amount ?: (int) $application->requested_amount,
            'requested_tenure_months' => $invitation->requested_tenure_months ?: (int) $application->requested_tenure_months,
        ]);

        $invitation = $invitation->fresh();
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

            $this->notifyInternalGuarantorRequest($borrower, $member, $link, $invitation, $application);

            $this->notifyBorrowerInvitationSent(
                $borrower,
                $invitation,
                trim($member->first_name.' '.$member->last_name),
            );

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

            $created = false;
            if ($invitation?->customer_guarantor_id) {
                $link = CustomerGuarantor::query()->find($invitation->customer_guarantor_id);
                if ($link?->guarantor_id) {
                    Guarantor::query()->where('id', $link->guarantor_id)->update([
                        'first_name'   => $member->first_name,
                        'last_name'    => $member->last_name,
                        'phone'        => $member->phone ?? '',
                        'email'        => $member->email,
                        'national_id'  => $member->national_id,
                        'address'      => $member->address,
                        'relationship' => 'member',
                    ]);
                }

                $invitation->update([
                    'guarantor_customer_id'   => $member->id,
                    'membership_id'           => MemberNumberFormatter::lookupKey($membershipId),
                    'invitee_name'            => $member->full_name,
                    'requested_amount'        => $requestedAmount,
                    'requested_tenure_months' => $requestedTenureMonths,
                    'loan_product_id'         => $loanProductId,
                    'channel'                 => 'in_app',
                    'contact'                 => $member->phone ?? '',
                    'expires_at'              => now()->addDays($this->invitationExpiryDays()),
                    'status'                  => ($link?->status === 'approved' || $invitation->status === 'accepted')
                        ? 'accepted'
                        : 'pending',
                ]);
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
                $created = true;
            }

            $this->ensureShortCode($invitation);

            $shouldNotify = $created || ($link && $link->status === 'pending');
            if ($shouldNotify && $link) {
                $invitationId = (int) $invitation->id;
                $borrowerId = (int) $borrower->id;
                $memberId = (int) $member->id;
                $linkId = (int) $link->id;

                DB::afterCommit(function () use ($invitationId, $borrowerId, $memberId, $linkId): void {
                    $freshInvitation = GuarantorInvitation::query()->find($invitationId);
                    $freshBorrower = Customer::query()->find($borrowerId);
                    $freshMember = Customer::query()->find($memberId);
                    $freshLink = CustomerGuarantor::query()->find($linkId);
                    if (! $freshInvitation || ! $freshBorrower || ! $freshMember || ! $freshLink) {
                        return;
                    }

                    try {
                        $this->notifyInternalGuarantorRequest($freshBorrower, $freshMember, $freshLink, $freshInvitation, null);
                        $this->notifyBorrowerInvitationSent(
                            $freshBorrower,
                            $freshInvitation,
                            trim($freshMember->first_name.' '.$freshMember->last_name),
                        );
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
            }

            $status = $this->borrowerInvitationStatus($invitation->fresh(['customerGuarantor']));

            return [
                'invitation_id'         => (int) $invitation->id,
                'customer_guarantor_id' => (int) ($link?->id ?? $invitation->customer_guarantor_id),
                'status'                => (string) $invitation->status,
                'borrower_status_code'  => $status['code'],
                'borrower_status_label' => $status['label'],
                'profile_percent'       => $status['profile_percent'],
                'accepted'              => $status['accepted'],
                'ready'                 => $status['ready'],
                'steps'                 => $status['steps'],
                'notified'              => $shouldNotify,
                'invitee_name'          => (string) ($invitation->invitee_name ?: $member->full_name),
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
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $invitation->loan_product_id ?: $application->loan_product_id,
            'requested_amount'        => $invitation->requested_amount ?: (int) $application->requested_amount,
            'requested_tenure_months' => $invitation->requested_tenure_months ?: (int) $application->requested_tenure_months,
        ]);

        return [$link->fresh(), $invitation->fresh()];
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
            'first_name'   => $member->first_name,
            'last_name'    => $member->last_name,
            'phone'        => $member->phone ?? '',
            'email'        => $member->email,
            'national_id'  => $member->national_id,
            'address'      => $member->address,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $guarantor->id,
            'loan_application_id' => $applicationId,
            'status'              => 'pending',
        ]);

        $invitation = GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $applicationId,
            'loan_product_id'         => $loanProductId,
            'customer_guarantor_id'   => $link->id,
            'guarantor_customer_id'   => $member->id,
            'type'                    => 'internal',
            'channel'                 => 'in_app',
            'contact'                 => $member->phone ?? '',
            'membership_id'           => MemberNumberFormatter::lookupKey($membershipId),
            'invitee_name'            => $member->full_name,
            'requested_amount'        => $requestedAmount,
            'requested_tenure_months' => $requestedTenureMonths,
            'token'                   => Str::random(48),
            'short_code'              => $this->generateShortCode(),
            'status'                  => 'pending',
            'expires_at'              => now()->addDays($this->invitationExpiryDays()),
        ]);

        return [$link, $invitation];
    }

    private function notifyInternalGuarantorRequest(
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
        $alreadyNotified = \App\Models\NotificationLog::query()
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
                'borrower'  => $borrowerName,
                'reference' => $reference,
            ]),
            'guarantor',
            'guarantor_request',
            __('borrower.guarantor_invite.notify_request_title'),
            route('site.borrower.guarantor-requests.show', $link),
            __('borrower.guarantor_notifications.view_request'),
            [
                'title_key' => 'borrower.guarantor_invite.notify_request_title',
                'body_key'  => 'borrower.guarantor_invite.guarantor_received',
                'params'    => [
                    'borrower'  => $borrowerName,
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

        return DB::transaction(function () use ($borrower, $application, $firstName, $middleName, $lastName, $phone, $email, $relationship, $region, $district, $channel, $displayName, $address): array {
            $guarantor = Guarantor::create([
                'first_name'   => trim($firstName.' '.($middleName ?: '')),
                'last_name'    => $lastName,
                'phone'        => $phone,
                'email'        => $email,
                'relationship' => $relationship,
                'address'      => $address,
            ]);

            $link = CustomerGuarantor::create([
                'customer_id'         => $borrower->id,
                'guarantor_id'        => $guarantor->id,
                'loan_application_id' => $application->id,
                'status'              => 'pending',
            ]);

            $contact = $channel === 'email' ? ($email ?: $phone) : $phone;

            $invitation = GuarantorInvitation::create([
                'customer_id'             => $borrower->id,
                'loan_application_id'     => $application->id,
                'loan_product_id'         => $application->loan_product_id,
                'customer_guarantor_id'   => $link->id,
                'type'                    => 'external',
                'channel'                 => $channel,
                'contact'                 => $contact,
                'invitee_name'            => $displayName,
                'requested_amount'        => (int) $application->requested_amount,
                'requested_tenure_months' => (int) $application->requested_tenure_months,
                'token'                   => Str::random(48),
                'short_code'              => $this->generateShortCode(),
                'status'                  => 'pending',
                'expires_at'              => now()->addDays($this->invitationExpiryDays()),
            ]);

            $this->notifyExternalInvitation($borrower, $invitation, $displayName);
            $this->notifyBorrowerInvitationSent($borrower, $invitation, $displayName);

            return [$link, $invitation];
        });
    }

    protected function notifyBorrowerInvitationSent(Customer $borrower, GuarantorInvitation $invitation, string $inviteeName): void
    {
        $context = $this->invitationLoanContext($invitation);
        $message = __('borrower.guarantor_invite.borrower_sent', [
            'guarantor' => $inviteeName,
            'product'   => $context['product_name'],
            'amount'    => $context['amount_label'],
            'duration'  => $context['duration_label'],
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
                'body_key'  => 'borrower.guarantor_invite.borrower_sent',
                'params'    => [
                    'guarantor' => $inviteeName,
                    'product'   => $context['product_name'],
                    'amount'    => $context['amount_label'],
                    'duration'  => $context['duration_label'],
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

    protected function notifyExternalInvitation(Customer $borrower, GuarantorInvitation $invitation, string $inviteeName): void
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
                ->update([
                    'status'       => 'accepted',
                    'responded_at' => now(),
                ]);

            $this->tryReleaseApplicationFromGuarantorHold($link->application ?? $link->fresh()->application);
        });
    }

    /**
     * Move awaiting_guarantor → screening only when every approved guarantor has a complete profile.
     */
    public function tryReleaseApplicationFromGuarantorHold(?LoanApplication $application): bool
    {
        if (! $application || $application->status !== 'awaiting_guarantor') {
            return false;
        }

        $approvedLinks = CustomerGuarantor::query()
            ->with('invitation')
            ->where('loan_application_id', $application->id)
            ->where('status', 'approved')
            ->get();

        if ($approvedLinks->isEmpty()) {
            return false;
        }

        $onboarding = app(GuarantorOnboardingService::class);
        $access = app(GuarantorAccessService::class);

        foreach ($approvedLinks as $approvedLink) {
            $guarantorCustomer = $access->guarantorCustomerForLink($approvedLink);
            if (! $guarantorCustomer || ! ($onboarding->guarantorProfileStatus($guarantorCustomer)['met'] ?? false)) {
                return false;
            }
        }

        $application->update([
            'status'                => 'submitted',
            'current_stage'         => 'screening',
            'submitted_at'          => $application->submitted_at ?? now(),
            'guarantor_deadline_at' => null,
        ]);

        return true;
    }

    /** After a guarantor finishes their profile, release any held applications they already accepted. */
    public function releaseHeldApplicationsForGuarantor(Customer $guarantor): int
    {
        $onboarding = app(GuarantorOnboardingService::class);
        if (! ($onboarding->guarantorProfileStatus($guarantor)['met'] ?? false)) {
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

    public function reject(CustomerGuarantor $link, ?string $notes = null): void
    {
        DB::transaction(function () use ($link, $notes): void {
            $link->update(['status' => 'rejected']);

            $invitation = GuarantorInvitation::query()
                ->where('customer_guarantor_id', $link->id)
                ->where('status', 'pending')
                ->first();

            GuarantorInvitation::query()
                ->where('customer_guarantor_id', $link->id)
                ->where('status', 'pending')
                ->update([
                    'status'         => 'rejected',
                    'responded_at'   => now(),
                    'response_notes' => $notes,
                ]);

            $borrower = $link->customer;
            $guarantorName = trim((string) ($invitation?->invitee_name ?: $link->guarantor?->first_name.' '.$link->guarantor?->last_name));
            if ($borrower) {
                $applicationId = $link->loan_application_id ?? $invitation?->loan_application_id;
                $actionUrl = $applicationId
                    ? route('site.borrower.application', $applicationId)
                    : null;

                if (! $actionUrl && $invitation?->loan_product_id) {
                    $draft = app(LoanApplicationDraftService::class)
                        ->find($borrower, (int) $invitation->loan_product_id);
                    $actionUrl = $draft
                        ? route('site.borrower.loan-profile.draft', $draft)
                        : route('site.borrower.apply', [
                            'product' => $invitation->loan_product_id,
                            'resume' => 1,
                        ]);
                }

                app(NotificationService::class)->notifyInApp(
                    $borrower,
                    __('borrower.guarantor_invite.borrower_declined', ['guarantor' => trim($guarantorName)]),
                    'guarantor',
                    'guarantor_declined',
                    __('borrower.guarantor_invite.notify_declined_title'),
                    $actionUrl ?: route('site.borrower.loans', ['tab' => 'applications']),
                    __('borrower.notifications.view_application'),
                    [
                        'title_key' => 'borrower.guarantor_invite.notify_declined_title',
                        'body_key'  => 'borrower.guarantor_invite.borrower_declined',
                        'params'    => ['guarantor' => trim($guarantorName)],
                    ],
                );
            }
        });
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
                    'status'         => 'rejected',
                    'responded_at'   => now(),
                    'response_notes' => $notes ?: 'Declined by underwriting for this application',
                ]);
        });
    }

    public function hasApprovedGuarantor(LoanApplication $application): bool
    {
        return CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('status', 'approved')
            ->exists();
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
                'id'            => $link->id,
                'label'         => $label,
                'mode'          => $mode,
                'membership_id' => $invitation?->membership_id,
                'phone'         => $guarantor->phone ?: ($member?->phone ?? ''),
                'name'          => $label,
                'kyc_fresh'     => $kycFresh,
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
                        'ok'      => true,
                        'message' => __('borrower.apply.previous_guarantor.ready'),
                        'lookup'  => [
                            'ok'    => true,
                            'name'  => $verified['name'] ?? $verified['label'] ?? $link->guarantor->first_name,
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
            'ok'      => true,
            'message' => __('borrower.apply.previous_guarantor.request_sent'),
            'lookup'  => [
                'ok'    => true,
                'name'  => trim(($link->guarantor->first_name ?? '').' '.($link->guarantor->last_name ?? '')),
                'label' => trim(($link->guarantor->first_name ?? '').' '.($link->guarantor->last_name ?? '')),
                'member_no' => $invitation?->membership_id,
                'previous_guarantor_id' => $link->id,
                'kyc_fresh' => false,
            ],
        ];
    }
}
