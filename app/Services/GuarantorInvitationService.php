<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
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

        if (! $this->namesMatch($name, $member)) {
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
        $invitation->loadMissing(['borrower', 'application.product']);
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
    }

    /** @return array{amount: int, amount_label: string, tenure_months: int, duration_label: string, product_name: string} */
    public function invitationLoanContext(GuarantorInvitation $invitation): array
    {
        $invitation->loadMissing('application.product');
        $amount = (int) ($invitation->application?->requested_amount ?? $invitation->requested_amount ?? 0);
        $tenure = (int) ($invitation->application?->requested_tenure_months ?? $invitation->requested_tenure_months ?? 0);
        $productName = trim((string) ($invitation->application?->product?->name ?? ''));

        return [
            'amount'          => $amount,
            'amount_label'    => $amount > 0 ? 'TZS '.number_format($amount) : __('borrower.guarantor_invite.amount_tbd'),
            'tenure_months'   => $tenure,
            'duration_label'  => $tenure > 0
                ? __('borrower.guarantor_invite.duration_months', ['count' => $tenure])
                : __('borrower.guarantor_invite.duration_tbd'),
            'product_name'    => $productName !== '' ? $productName : __('borrower.guarantor_invite.product_tbd'),
        ];
    }

    public function guarantorLinkStatusLabel(CustomerGuarantor $link): string
    {
        $link->loadMissing('guarantor');
        if ($link->status === 'approved') {
            return __('borrower.apply.guarantor_status.approved');
        }
        if ($link->status === 'rejected') {
            return __('borrower.apply.guarantor_status.rejected');
        }

        $invitation = GuarantorInvitation::query()
            ->where('customer_guarantor_id', $link->id)
            ->latest()
            ->first();

        if ($invitation?->status === 'accepted' && $link->type === 'external') {
            return __('borrower.apply.guarantor_status.pending_profile');
        }

        if (in_array($invitation?->status, ['pending', 'accepted'], true)) {
            return __('borrower.apply.guarantor_status.pending_acceptance');
        }

        return __('borrower.apply.guarantor_status.pending');
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

    /** @return array{invitation_url: string, short_url: string, whatsapp_url: string|null, sms_url: string|null, email_url: string|null, invitation_id: int} */
    public function sharePayload(GuarantorInvitation $invitation, ?Customer $borrower = null): array
    {
        $borrower ??= $invitation->borrower;

        return [
            'invitation_id'  => $invitation->id,
            'invitation_url' => $this->invitationUrl($invitation),
            'short_url'      => $this->shortInvitationUrl($invitation),
            'whatsapp_url'   => $this->whatsAppShareUrl($invitation, $borrower),
            'sms_url'        => $this->smsShareUrl($invitation),
            'email_url'      => $this->emailShareUrl($invitation),
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
    ): array {
        $phone = $this->normalizePhone($phone);
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
        ): array {
            $invitation = null;
            if ($existingInvitationId) {
                $invitation = GuarantorInvitation::query()
                    ->where('id', $existingInvitationId)
                    ->where('customer_id', $borrower->id)
                    ->where('type', 'external')
                    ->whereNull('loan_application_id')
                    ->where('status', 'pending')
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
                $invitation->update([
                    'channel'                 => $channel,
                    'contact'                 => $contact,
                    'invitee_name'            => $displayName,
                    'requested_amount'        => $requestedAmount,
                    'requested_tenure_months' => $requestedTenureMonths,
                    'expires_at'              => now()->addDays(14),
                ]);
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
                    'expires_at'              => now()->addDays(14),
                ]);
            }

            $this->ensureShortCode($invitation);
            $this->notifyExternalInvitation($borrower, $invitation, $displayName);

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
            ->where('status', 'pending')
            ->firstOrFail();

        $link = CustomerGuarantor::query()->findOrFail($invitation->customer_guarantor_id);
        $link->update(['loan_application_id' => $application->id]);
        $invitation->update([
            'loan_application_id'     => $application->id,
            'requested_amount'        => $invitation->requested_amount ?: (int) $application->requested_amount,
            'requested_tenure_months' => $invitation->requested_tenure_months ?: (int) $application->requested_tenure_months,
        ]);

        return $invitation->fresh();
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

        return DB::transaction(function () use ($borrower, $application, $member, $membershipId): array {
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
                'loan_application_id' => $application->id,
                'status'              => 'pending',
            ]);

            $invitation = GuarantorInvitation::create([
                'customer_id'             => $borrower->id,
                'loan_application_id'     => $application->id,
                'customer_guarantor_id'   => $link->id,
                'guarantor_customer_id'   => $member->id,
                'type'                    => 'internal',
                'membership_id'           => MemberNumberFormatter::lookupKey($membershipId),
                'invitee_name'            => $member->full_name,
                'requested_amount'        => (int) $application->requested_amount,
                'requested_tenure_months' => (int) $application->requested_tenure_months,
                'token'                   => Str::random(48),
                'short_code'              => $this->generateShortCode(),
                'status'                  => 'pending',
                'expires_at'              => now()->addDays(14),
            ]);

            $borrowerName = trim($borrower->first_name.' '.$borrower->last_name);
            $productName = $application->product?->name ?? 'loan';
            $context = $this->invitationLoanContext($invitation);
            app(NotificationService::class)->notifyInApp(
                $member,
                __('borrower.guarantor_invite.in_app_request', [
                    'borrower' => $borrowerName,
                    'product'  => $productName,
                    'amount'   => $context['amount_label'],
                    'duration' => $context['duration_label'],
                ]),
                'guarantor',
                'guarantor_request',
            );

            return [$link, $invitation];
        });
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
                'expires_at'              => now()->addDays(14),
            ]);

            $this->notifyExternalInvitation($borrower, $invitation, $displayName);

            return [$link, $invitation];
        });
    }

    protected function notifyExternalInvitation(Customer $borrower, GuarantorInvitation $invitation, string $inviteeName): void
    {
        $borrowerName = trim($borrower->first_name.' '.$borrower->last_name);
        $message = $this->invitationMessage($invitation);
        $invitation->loadMissing('customerGuarantor.guarantor');
        $email = trim((string) ($invitation->customerGuarantor?->guarantor?->email ?? ''));

        if ($invitation->channel === 'email' && $email !== '') {
            app(NotificationService::class)->sendEmail(
                $email,
                __('borrower.guarantor_invite.email_subject', ['borrower' => $borrowerName]),
                $message,
                $borrower,
                'guarantor_invite',
            );
        } elseif ($invitation->contact) {
            app(NotificationService::class)->sendSms((string) $invitation->contact, $message, $borrower, 'guarantor_invite');
        }
    }

    public function approve(CustomerGuarantor $link): void
    {
        DB::transaction(function () use ($link): void {
            $link->update(['status' => 'approved']);

            GuarantorInvitation::query()
                ->where('customer_guarantor_id', $link->id)
                ->where('status', 'pending')
                ->update([
                    'status'        => 'accepted',
                    'responded_at'  => now(),
                ]);

            $application = $link->application;
            if ($application && $application->status === 'awaiting_guarantor') {
                $hasApproved = CustomerGuarantor::query()
                    ->where('loan_application_id', $application->id)
                    ->where('status', 'approved')
                    ->exists();

                if ($hasApproved) {
                    $application->update([
                        'status'        => 'submitted',
                        'current_stage' => 'submitted',
                        'submitted_at'  => $application->submitted_at ?? now(),
                    ]);
                }
            }
        });
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
                app(NotificationService::class)->notifyInApp(
                    $borrower,
                    __('borrower.guarantor_invite.borrower_declined', ['guarantor' => trim($guarantorName)]),
                    'guarantor',
                    'guarantor_declined',
                );
            }
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
}
