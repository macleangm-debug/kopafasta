<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GroupMemberInvitation;
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
}
