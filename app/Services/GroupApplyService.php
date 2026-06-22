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
     * @return array{ok: bool, message?: string, customer_id?: int, name?: string, phone?: string, label?: string}
     */
    public function lookupMemberByPhone(Customer $leader, string $phone): array
    {
        $normalized = $this->guarantors->normalizePhone($phone);
        if ($normalized === '') {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.group.lookup_invalid_phone'),
            ];
        }

        $member = Customer::query()
            ->where('status', 'active')
            ->whereNotNull('phone')
            ->get()
            ->first(fn (Customer $customer) => $this->guarantors->normalizePhone($customer->phone) === $normalized);

        if (! $member) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.group.lookup_not_found'),
            ];
        }

        if ((int) $member->id === (int) $leader->id) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.group.lookup_self'),
            ];
        }

        if (! $member->hasMembership()) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.group.lookup_not_member'),
            ];
        }

        if (! $member->isMembershipActive() && ! $member->isMembershipInGrace()) {
            return [
                'ok'      => false,
                'message' => __('borrower.apply.group.lookup_inactive'),
            ];
        }

        $name = trim(($member->first_name ?? '').' '.($member->last_name ?? ''));

        $status = app(GroupMemberProgressService::class)->statusFromCustomer($member);

        return [
            'ok'          => true,
            'customer_id' => $member->id,
            'name'        => $name,
            'phone'       => $member->phone,
            'label'       => trim($name.' · '.($member->customer_number ?: $member->phone)),
            'status_key'  => $status['key'],
        ];
    }

    /**
     * @param  array{name?: string, purpose?: string, members?: list<array<string, mixed>>}  $group
     * @return array{name: string, purpose: string, members: list<array{customer_id: int, role?: string, requested_amount?: float}>}
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

        $members = $rawMembers->map(function (array $row) use ($leader): array {
            $invitationId = (int) ($row['invitation_id'] ?? 0);
            $customerId = (int) ($row['customer_id'] ?? 0);
            $amount = (float) ($row['requested_amount'] ?? 0);

            if ($amount < 1000) {
                throw ValidationException::withMessages([
                    'group.members' => __('borrower.apply.group.amount_required'),
                ]);
            }

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
                'requested_amount' => $amount,
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

        $this->groups->validateMemberCount($members->count());

        $targetCount = (int) ($group['target_member_count'] ?? 0);
        if ($targetCount > 0 && $members->count() !== $targetCount) {
            throw ValidationException::withMessages([
                'group.members' => __('borrower.apply.group.members_required'),
            ]);
        }

        $total = $members->sum('requested_amount');
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
            'target_member_count' => $targetCount > 0 ? $targetCount : $members->count(),
            'amount_per_member'   => (float) ($group['amount_per_member'] ?? $members->first()['requested_amount'] ?? 0),
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
