<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\Setting;
use App\Support\MemberNumberFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuarantorInvitationService
{
    public function findMemberByNumber(string $membershipId): ?Customer
    {
        $key = MemberNumberFormatter::lookupKey($membershipId);

        if (! $key) {
            return null;
        }

        return Customer::query()
            ->where('member_no', $key)
            ->whereNotNull('membership_expires_at')
            ->first();
    }

    public function invitationUrl(GuarantorInvitation $invitation): string
    {
        $base = app(ReferralService::class)->appBaseUrl();

        return $base.'/guarantor/'.$invitation->token;
    }

    public function invitationMessage(GuarantorInvitation $invitation): string
    {
        $url = $this->invitationUrl($invitation);

        return "Hello,\n\nI have listed you as my guarantor for a KopaFasta loan application.\n\nPlease review and respond using the link below:\n\n{$url}\n\nThank you.";
    }

    public function whatsAppShareUrl(GuarantorInvitation $invitation, Customer $borrower): ?string
    {
        if ($invitation->channel !== 'whatsapp' || ! $invitation->contact) {
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

        $subject = 'KopaFasta guarantor invitation';
        $body = $this->invitationMessage($invitation);

        return 'mailto:'.$email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($body);
    }

    /** @return array{invitation_url: string, whatsapp_url: string|null, sms_url: string|null, email_url: string|null, invitation_id: int} */
    public function sharePayload(GuarantorInvitation $invitation, ?Customer $borrower = null): array
    {
        $borrower ??= $invitation->borrower;

        return [
            'invitation_id'  => $invitation->id,
            'invitation_url' => $this->invitationUrl($invitation),
            'whatsapp_url'   => $this->whatsAppShareUrl($invitation, $borrower),
            'sms_url'        => $this->smsShareUrl($invitation),
            'email_url'      => $this->emailShareUrl($invitation),
        ];
    }

    /**
     * Create or refresh a pending external invitation before the loan application is submitted.
     *
     * @return array{invitation_id: int, invitation_url: string, whatsapp_url: string|null, sms_url: string|null, email_url: string|null}
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
                    'channel'      => $channel,
                    'contact'      => $contact,
                    'invitee_name' => $displayName,
                    'expires_at'   => now()->addDays(14),
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
                    'customer_id'           => $borrower->id,
                    'loan_application_id'   => null,
                    'customer_guarantor_id' => $link->id,
                    'type'                  => 'external',
                    'channel'               => $channel,
                    'contact'               => $contact,
                    'invitee_name'          => $displayName,
                    'token'                 => Str::random(48),
                    'status'                => 'pending',
                    'expires_at'            => now()->addDays(14),
                ]);
            }

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
        $invitation->update(['loan_application_id' => $application->id]);

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
    ): array {
        $member = $this->findMemberByNumber($membershipId);

        if (! $member) {
            throw new \InvalidArgumentException('No active member found with that membership number.');
        }

        if ($member->id === $borrower->id) {
            throw new \InvalidArgumentException('You cannot guarantee your own loan.');
        }

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
                'customer_id'           => $borrower->id,
                'loan_application_id'   => $application->id,
                'customer_guarantor_id' => $link->id,
                'guarantor_customer_id' => $member->id,
                'type'                  => 'internal',
                'membership_id'         => MemberNumberFormatter::lookupKey($membershipId),
                'invitee_name'          => $member->full_name,
                'token'                 => Str::random(48),
                'status'                => 'pending',
                'expires_at'            => now()->addDays(14),
            ]);

            $borrowerName = trim($borrower->first_name.' '.$borrower->last_name);
            $productName = $application->product?->name ?? 'loan';
            app(NotificationService::class)->notifyInApp(
                $member,
                "{$borrowerName} asked you to guarantee their {$productName} application. Review and respond in Guarantor requests.",
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
                'customer_id'           => $borrower->id,
                'loan_application_id'   => $application->id,
                'customer_guarantor_id' => $link->id,
                'type'                  => 'external',
                'channel'               => $channel,
                'contact'               => $contact,
                'invitee_name'          => $displayName,
                'token'                 => Str::random(48),
                'status'                => 'pending',
                'expires_at'            => now()->addDays(14),
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
                'Guarantor invitation from '.$borrowerName,
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

            GuarantorInvitation::query()
                ->where('customer_guarantor_id', $link->id)
                ->where('status', 'pending')
                ->update([
                    'status'         => 'rejected',
                    'responded_at'   => now(),
                    'response_notes' => $notes,
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
}
