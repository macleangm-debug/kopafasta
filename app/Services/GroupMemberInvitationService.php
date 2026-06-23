<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupMemberInvitationService
{
    public function __construct(
        protected GuarantorInvitationService $guarantors,
    ) {}

    /**
     * @return array{invitation_id: int, invitation_url: string, short_url: string, whatsapp_url: string|null, sms_url: string|null, name: string, phone: string}
     */
    public function prepareExternalInvitation(
        Customer $leader,
        LoanProduct $product,
        string $firstName,
        ?string $middleName,
        string $lastName,
        string $phone,
        ?string $email = null,
        ?int $existingInvitationId = null,
    ): array {
        $phone = $this->guarantors->normalizePhone($phone);
        if ($phone === '') {
            throw new \InvalidArgumentException(__('borrower.apply.group.lookup_invalid_phone'));
        }

        if ($member = $this->guarantors->findMemberCustomerByPhone($phone)) {
            throw new \InvalidArgumentException(__('borrower.apply.group.lookup_is_member', [
                'name' => trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
            ]));
        }

        $displayName = trim(collect([$firstName, $middleName, $lastName])->filter()->implode(' '));

        return DB::transaction(function () use (
            $leader,
            $product,
            $firstName,
            $middleName,
            $lastName,
            $phone,
            $email,
            $displayName,
            $existingInvitationId,
        ): array {
            $invitation = null;
            if ($existingInvitationId) {
                $invitation = GroupMemberInvitation::query()
                    ->where('id', $existingInvitationId)
                    ->where('leader_customer_id', $leader->id)
                    ->whereIn('status', ['pending', 'accepted'])
                    ->first();
            }

            if (! $invitation) {
                $invitation = GroupMemberInvitation::create([
                    'leader_customer_id' => $leader->id,
                    'loan_product_id'    => $product->id,
                    'invitee_first_name' => $firstName,
                    'invitee_middle_name'=> $middleName,
                    'invitee_last_name'  => $lastName,
                    'invitee_phone'      => $phone,
                    'invitee_email'      => $email,
                    'token'              => Str::random(48),
                    'short_code'         => Str::upper(Str::random(8)),
                    'status'             => 'pending',
                    'expires_at'         => now()->addDays(14),
                ]);
            } else {
                $invitation->update([
                    'invitee_first_name' => $firstName,
                    'invitee_middle_name'=> $middleName,
                    'invitee_last_name'  => $lastName,
                    'invitee_phone'      => $phone,
                    'invitee_email'      => $email,
                    'status'             => 'pending',
                    'expires_at'         => now()->addDays(14),
                ]);
            }

            $share = $this->sharePayload($invitation->fresh());

            app(NotificationService::class)->sendSms(
                $phone,
                __('borrower.apply.group.invite_message', [
                    'leader' => $leader->full_name ?? brand_name(),
                    'url'    => $share['short_url'],
                ]),
                null,
                'group_member_external_invite',
            );

            return array_merge($share, [
                'name'  => $displayName,
                'phone' => $phone,
            ]);
        });
    }

    /** @return array{invitation_id: int, invitation_url: string, short_url: string, whatsapp_url: string|null, sms_url: string|null, email_url: string|null} */
    public function sharePayload(GroupMemberInvitation $invitation): array
    {
        $url = route('site.group-member.invite', ['token' => $invitation->token]);
        $shortUrl = $invitation->short_code
            ? url('/g/'.$invitation->short_code)
            : $url;

        $message = __('borrower.apply.group.invite_message', [
            'leader' => $invitation->leader?->full_name ?? brand_name(),
            'url'    => $shortUrl,
        ]);

        $phoneDigits = preg_replace('/\D/', '', $invitation->invitee_phone) ?? '';

        return [
            'invitation_id'  => $invitation->id,
            'invitation_url' => $url,
            'short_url'      => $shortUrl,
            'whatsapp_url'   => $phoneDigits !== ''
                ? 'https://wa.me/'.$phoneDigits.'?text='.urlencode($message)
                : null,
            'sms_url'        => $phoneDigits !== ''
                ? 'sms:'.$phoneDigits.'?body='.urlencode($message)
                : null,
            'email_url'      => filled($invitation->invitee_email)
                ? 'mailto:'.$invitation->invitee_email.'?subject='.urlencode(__('borrower.apply.group.invite_subject')).'&body='.urlencode($message)
                : null,
        ];
    }

    /** @return array{invitation_id: int, invitation_url: string, short_url: string, whatsapp_url: string|null, sms_url: string|null, email_url: string|null, customer_id: int, name: string, phone: string, status_key: string} */
    public function prepareInternalInvitation(
        Customer $leader,
        LoanProduct $product,
        Customer $member,
    ): array {
        if ((int) $member->id === (int) $leader->id) {
            throw new \InvalidArgumentException(__('borrower.apply.group.lookup_self'));
        }

        $invitation = GroupMemberInvitation::query()
            ->where('leader_customer_id', $leader->id)
            ->where('customer_id', $member->id)
            ->where('loan_product_id', $product->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $invitation) {
            $invitation = GroupMemberInvitation::create([
                'leader_customer_id'  => $leader->id,
                'loan_product_id'     => $product->id,
                'customer_id'         => $member->id,
                'invitee_first_name'  => $member->first_name,
                'invitee_middle_name' => $member->middle_name,
                'invitee_last_name'   => $member->last_name,
                'invitee_phone'       => $this->guarantors->normalizePhone($member->phone ?? ''),
                'invitee_email'       => $member->email,
                'membership_id'       => \App\Support\MemberNumberFormatter::lookupKey($member->member_no ?? ''),
                'token'               => Str::random(48),
                'short_code'          => Str::upper(Str::random(8)),
                'status'              => 'accepted',
                'expires_at'          => now()->addDays(14),
                'responded_at'        => now(),
            ]);
        }

        app(GroupLoanNotificationService::class)->notifyInternalMemberConsent($member, $leader);

        $status = app(GroupMemberProgressService::class)->statusFromInvitation($invitation->fresh());

        return array_merge($this->sharePayload($invitation), [
            'customer_id' => $member->id,
            'name'        => $member->full_name,
            'phone'       => $member->phone,
            'status_key'  => $status['key'],
        ]);
    }

    /**
     * @return list<array{customer_id: int, name: string, phone: string, label: string}>
     */
    public function previousMembersForLeader(Customer $leader): array
    {
        $seen = [];
        $items = [];

        $invitations = GroupMemberInvitation::query()
            ->where('leader_customer_id', $leader->id)
            ->whereNotNull('customer_id')
            ->where('customer_id', '!=', $leader->id)
            ->whereIn('status', ['accepted', 'completed'])
            ->with('customer')
            ->latest('id')
            ->get();

        foreach ($invitations as $invitation) {
            $member = $invitation->customer;
            if (! $member) {
                continue;
            }

            $key = (string) $member->id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $name = $member->full_name;
            $items[] = [
                'customer_id' => $member->id,
                'name'        => $name,
                'phone'       => $member->phone ?? '',
                'label'       => trim($name.' · '.($member->customer_number ?: $member->phone)),
            ];
        }

        return $items;
    }

    /** @return array{ok: bool, message?: string, customer_id?: int, name?: string, phone?: string, invitation_id?: int, status_key?: string, share?: array<string, mixed>} */
    public function preparePreviousMember(
        Customer $leader,
        LoanProduct $product,
        int $customerId,
    ): array {
        $member = Customer::query()
            ->where('id', $customerId)
            ->where('status', 'active')
            ->first();

        if (! $member) {
            return ['ok' => false, 'message' => __('borrower.apply.group.lookup_not_found')];
        }

        $usedBefore = GroupMemberInvitation::query()
            ->where('leader_customer_id', $leader->id)
            ->where('customer_id', $member->id)
            ->exists();

        if (! $usedBefore) {
            return ['ok' => false, 'message' => __('borrower.apply.group.previous_member_not_found')];
        }

        try {
            $share = $this->prepareInternalInvitation($leader, $product, $member);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return [
            'ok'            => true,
            'customer_id'   => $share['customer_id'],
            'name'          => $share['name'],
            'phone'         => $share['phone'],
            'invitation_id' => $share['invitation_id'],
            'status_key'    => $share['status_key'],
            'share'         => $share,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $memberRows
     * @return list<array{customer_id: int, role?: string, requested_amount?: float, invitation_id?: int}>
     */
    public function resolveMembersForSubmit(Customer $leader, array $memberRows): array
    {
        return collect($memberRows)->map(function (array $row) use ($leader): array {
            $customerId = (int) ($row['customer_id'] ?? 0);
            $isLeader = ($row['role'] ?? '') === 'leader' || $customerId === (int) $leader->id;

            if ($isLeader) {
                return [
                    'customer_id'      => $customerId ?: (int) $leader->id,
                    'role'             => 'leader',
                    'requested_amount' => (float) ($row['requested_amount'] ?? 0),
                ];
            }

            $invitation = $this->resolveInvitationForRow($leader, $row);
            if (! $invitation) {
                throw new \InvalidArgumentException(__('borrower.apply.group.member_consent_required'));
            }

            if (! $invitation->customer_id) {
                throw new \InvalidArgumentException(__('borrower.apply.group.member_not_registered', [
                    'name' => $invitation->displayName(),
                ]));
            }

            if ($invitation->status !== 'completed') {
                throw new \InvalidArgumentException(__('borrower.apply.group.member_not_ready', [
                    'name' => $invitation->displayName(),
                ]));
            }

            if (! app(GroupMemberSignatureService::class)->hasSignature($invitation)) {
                throw new \InvalidArgumentException(__('borrower.apply.group.member_signature_missing', [
                    'name' => $invitation->displayName(),
                ]));
            }

            return [
                'customer_id'      => (int) $invitation->customer_id,
                'role'             => 'member',
                'requested_amount' => (float) ($row['requested_amount'] ?? 0),
                'invitation_id'    => $invitation->id,
            ];
        })->values()->all();
    }

    /** @param array<string, mixed> $row */
    private function resolveInvitationForRow(Customer $leader, array $row): ?GroupMemberInvitation
    {
        $invitationId = (int) ($row['invitation_id'] ?? 0);
        if ($invitationId > 0) {
            return GroupMemberInvitation::query()
                ->where('id', $invitationId)
                ->where('leader_customer_id', $leader->id)
                ->first();
        }

        $customerId = (int) ($row['customer_id'] ?? 0);
        if ($customerId <= 0 || $customerId === (int) $leader->id) {
            return null;
        }

        return GroupMemberInvitation::query()
            ->where('leader_customer_id', $leader->id)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['accepted', 'completed'])
            ->latest('id')
            ->first();
    }

    public function attachSignaturesToApplication(LoanApplication $application, array $memberRows): void
    {
        $signatures = app(GroupMemberSignatureService::class);

        foreach ($memberRows as $row) {
            $invitationId = (int) ($row['invitation_id'] ?? 0);
            if ($invitationId <= 0) {
                continue;
            }

            $invitation = GroupMemberInvitation::find($invitationId);
            if ($invitation) {
                $signatures->attachToApplication($application, $invitation);
            }
        }
    }
}
