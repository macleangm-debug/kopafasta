<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanProduct;
use Illuminate\Validation\ValidationException;

class GroupApplyService
{
    public function __construct(
        protected GroupLendingService $groups,
        protected GuarantorInvitationService $guarantors,
    ) {}

    /** @return array{min: int, max: int} */
    public function memberLimits(): array
    {
        return $this->groups->memberLimits();
    }

    /**
     * @return array{ok: bool, message?: string, customer_id?: int, name?: string, phone?: string, label?: string, status_key?: string}
     */
    public function lookupMemberByMembershipAndPhone(
        Customer $leader,
        string $membershipId,
        string $phone,
        string $name = '',
    ): array {
        $verified = $this->guarantors->verifyInternalMember($leader, $membershipId, $phone, $name);

        if (! ($verified['ok'] ?? false)) {
            return [
                'ok'      => false,
                'message' => $verified['message'] ?? __('borrower.apply.group.lookup_not_found'),
            ];
        }

        $member = $verified['member'] ?? Customer::find((int) ($verified['customer_id'] ?? 0));
        if (! $member instanceof Customer) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.group.lookup_not_found'),
            ];
        }

        $status = app(GroupMemberProgressService::class)->statusFromCustomer($member);

        return [
            'ok'          => true,
            'customer_id' => $member->id,
            'name'        => $verified['name'] ?? $member->full_name,
            'phone'       => $member->phone,
            'label'       => $verified['label'] ?? trim(($member->full_name).' · '.($member->customer_number ?: $member->phone)),
            'status_key'  => $status['key'],
        ];
    }

    /** @deprecated Use lookupMemberByMembershipAndPhone() */
    public function lookupMemberByPhone(Customer $leader, string $phone): array
    {
        return $this->lookupMemberByMembershipAndPhone($leader, '', $phone);
    }

    /**
     * @param  array{name?: string, purpose?: string, members?: list<array<string, mixed>>, target_member_count?: int, amount_per_member?: float}  $group
     * @return array{name: string, purpose: string, target_member_count: int, amount_per_member: float, members: list<array{customer_id?: int, invitation_id?: int, role?: string, requested_amount?: float}>}
     */
    public function validateGroupPayload(Customer $leader, LoanProduct $product, array $group): array
    {
        if (! $this->groups->isGroupProduct($product)) {
            throw new \InvalidArgumentException('Not a group loan product.');
        }

        $name = trim((string) ($group['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'group.name' => __('borrower.apply.group.name_required'),
            ]);
        }

        $purpose = trim((string) ($group['purpose'] ?? ''));
        if ($purpose === '') {
            throw ValidationException::withMessages([
                'group.purpose' => __('borrower.apply.alerts.select_purpose'),
            ]);
        }

        $targetCount = max(1, (int) ($group['target_member_count'] ?? 0));
        $amountPerMember = (float) ($group['amount_per_member'] ?? 0);
        if ($amountPerMember < 1000) {
            throw ValidationException::withMessages([
                'group.amount_per_member' => __('borrower.apply.group.amount_required'),
            ]);
        }

        $rawMembers = collect($group['members'] ?? [])
            ->filter(fn ($row) => is_array($row) && (filled($row['customer_id'] ?? null) || filled($row['invitation_id'] ?? null)))
            ->values();

        $hasLeader = $rawMembers->contains(fn ($row) => (int) ($row['customer_id'] ?? 0) === (int) $leader->id)
            || $rawMembers->contains(fn ($row) => ($row['role'] ?? '') === 'leader');

        if (! $hasLeader) {
            throw ValidationException::withMessages([
                'group.members' => __('borrower.apply.group.leader_required'),
            ]);
        }

        $members = $rawMembers->map(function (array $row) use ($leader, $amountPerMember): array {
            $invitationId = (int) ($row['invitation_id'] ?? 0);
            $customerId = (int) ($row['customer_id'] ?? 0);

            if ($invitationId > 0) {
                $invitation = \App\Models\GroupMemberInvitation::query()
                    ->where('id', $invitationId)
                    ->where('leader_customer_id', $leader->id)
                    ->first();

                if (! $invitation) {
                    throw ValidationException::withMessages([
                        'group.members' => __('borrower.apply.group.invite_not_found'),
                    ]);
                }

                if ($invitation->customer_id) {
                    $customerId = (int) $invitation->customer_id;
                }
            }

            if ($customerId <= 0 && $invitationId <= 0) {
                throw ValidationException::withMessages([
                    'group.members' => __('borrower.apply.group.member_incomplete'),
                ]);
            }

            $resolved = [
                'requested_amount' => $amountPerMember,
                'role'             => $customerId === (int) $leader->id ? 'leader' : 'member',
            ];

            if ($customerId > 0) {
                $resolved['customer_id'] = $customerId;
            }
            if ($invitationId > 0) {
                $resolved['invitation_id'] = $invitationId;
            }

            if ($customerId > 0
                && $customerId !== (int) $leader->id
                && $invitationId <= 0) {
                throw ValidationException::withMessages([
                    'group.members' => __('borrower.apply.group.member_consent_required'),
                ]);
            }

            return $resolved;
        });

        $duplicateIds = $members->filter(fn ($row) => isset($row['customer_id']))
            ->pluck('customer_id')
            ->duplicates();
        if ($duplicateIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'group.members' => __('borrower.apply.group.duplicate_member'),
            ]);
        }

        $duplicateInvites = $members->filter(fn ($row) => isset($row['invitation_id']))
            ->pluck('invitation_id')
            ->duplicates();
        if ($duplicateInvites->isNotEmpty()) {
            throw ValidationException::withMessages([
                'group.members' => __('borrower.apply.group.duplicate_member'),
            ]);
        }

        if ($members->count() !== $targetCount) {
            throw ValidationException::withMessages([
                'group.members' => __('borrower.apply.group.members_required'),
            ]);
        }

        $this->groups->validateMemberCount($members->count());

        $total = $amountPerMember * $targetCount;
        if ($total < (float) $product->min_amount || $total > (float) $product->max_amount) {
            throw ValidationException::withMessages([
                'group.members' => __('borrower.apply.group.total_out_of_range', [
                    'min' => format_number($product->min_amount),
                    'max' => format_number($product->max_amount),
                ]),
            ]);
        }

        return [
            'name'                => $name,
            'purpose'             => $purpose,
            'target_member_count' => $targetCount,
            'amount_per_member'   => $amountPerMember,
            'members'             => $members->values()->all(),
        ];
    }

    /** @param  list<array{customer_id: int, role?: string, requested_amount?: float}>  $members */
    public function leaderMemberRow(Customer $leader, float $requestedAmount = 0): array
    {
        return [
            'customer_id'      => $leader->id,
            'name'             => $leader->full_name,
            'phone'            => $leader->phone,
            'role'             => 'leader',
            'requested_amount' => max(0, $requestedAmount),
        ];
    }
}
