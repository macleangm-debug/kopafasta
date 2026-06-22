<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\LoanGroupMember;
use App\Services\GroupApplyService;
use App\Services\GroupMemberReplacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupMemberReplacementController extends Controller
{
    use AuditsActions;

    public function replaceInternal(
        Request $request,
        LoanApplication $application,
        LoanGroupMember $loan_group_member,
        GroupMemberReplacementService $replacements,
        GroupApplyService $groups,
    ): JsonResponse {
        $leader = $this->leaderOrFail($application);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $lookup = $groups->lookupMemberByPhone($leader, $data['phone']);
        if (! ($lookup['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $lookup['message'] ?? __('borrower.apply.group.lookup_not_found')], 422);
        }

        $newMember = \App\Models\Customer::findOrFail((int) $lookup['customer_id']);

        try {
            $row = $replacements->replaceWithInternalMember($application, $leader, $loan_group_member, $newMember);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        app(\App\Services\GroupContractSignatureService::class)->notifyPendingMembers($application->fresh());

        $this->auditBorrower('group_member.replaced_internal', $application, [
            'old_member_id' => $loan_group_member->id,
            'new_member_id' => $row->id,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => __('borrower.apply.group.replacement_success'),
            'member'  => [
                'id'   => $row->id,
                'name' => $newMember->full_name,
            ],
        ]);
    }

    public function replaceExternal(
        Request $request,
        LoanApplication $application,
        LoanGroupMember $loan_group_member,
        GroupMemberReplacementService $replacements,
    ): JsonResponse {
        $leader = $this->leaderOrFail($application);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name'  => ['required', 'string', 'max:80'],
            'phone'      => ['required', 'string', 'max:20'],
            'email'      => ['nullable', 'email', 'max:150'],
        ]);

        try {
            $result = $replacements->replaceWithExternalInvite(
                $application,
                $leader,
                $loan_group_member,
                $data['first_name'],
                $data['last_name'],
                $data['phone'],
                $data['email'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $this->auditBorrower('group_member.replaced_external', $application, [
            'old_member_id' => $loan_group_member->id,
            'invitation_id' => $result['invitation_id'],
        ]);

        return response()->json([
            'ok'   => true,
            'share'=> $result['share'],
            'message' => __('borrower.apply.group.replacement_invite_ready'),
        ]);
    }

    private function leaderOrFail(LoanApplication $application): \App\Models\Customer
    {
        $customer = auth()->user()?->customer
            ?? \App\Models\Customer::where('user_id', auth()->id())->first();

        abort_unless($customer, 403);
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);

        return $customer;
    }
}
