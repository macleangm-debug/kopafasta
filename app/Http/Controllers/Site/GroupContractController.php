<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\ApplicationDisbursementReadinessService;
use App\Services\GroupContractSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupContractController extends Controller
{
    use AuditsActions;

    public function show(
        LoanApplication $application,
        GroupContractSignatureService $signatures,
        ApplicationDisbursementReadinessService $readiness,
    ): View|RedirectResponse {
        $customer = $this->customerOrFail();
        $member = $signatures->memberForCustomer($application, $customer);

        abort_unless($member, 404);
        abort_if($member->isLeader(), 403, __('borrower.apply.group.contract_leader_uses_main'));

        if (! $readiness->loanContract($application)) {
            return redirect()->route('site.borrower.dashboard')
                ->with('status', __('borrower.apply.group.contract_not_ready'));
        }

        $progress = $signatures->progress($application);
        $contract = $readiness->loanContract($application);
        $snap = $contract?->snapshot ?? [];

        return view('site.group-member.contract', [
            'application' => $application,
            'member'      => $member,
            'customer'    => $customer,
            'progress'    => $progress,
            'snap'        => $snap,
            'signed'      => $member->contract_signature_status === 'signed',
            'declined'    => $member->contract_signature_status === 'declined',
        ]);
    }

    public function sign(
        Request $request,
        LoanApplication $application,
        GroupContractSignatureService $signatures,
        ApplicationDisbursementReadinessService $readiness,
    ): RedirectResponse {
        $customer = $this->customerOrFail();
        $member = $signatures->memberForCustomer($application, $customer);

        abort_unless($member, 404);
        abort_if($member->isLeader(), 403);

        abort_unless($readiness->loanContract($application), 404);

        $data = $request->validate([
            'signer_name'    => ['required', 'string', 'max:120'],
            'signature_data' => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'consent'        => ['accepted'],
        ]);

        try {
            $signatures->recordSignature(
                $member,
                $customer,
                $data['signer_name'],
                $data['signature_data'],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $readiness->syncBorrowerProgress($application->fresh());

        $this->auditBorrower('group_contract.signed', $application, [
            'member_id' => $member->id,
        ]);

        $leader = $application->customer;
        if ($leader) {
            app(\App\Services\GroupLoanNotificationService::class)->notifyLeaderMemberSigned(
                $leader,
                $application,
                $customer->full_name,
            );
        }

        return redirect()
            ->route('site.borrower.group-contract.show', $application)
            ->with('status', __('borrower.apply.group.contract_signed'));
    }

    public function decline(
        Request $request,
        LoanApplication $application,
        GroupContractSignatureService $signatures,
        ApplicationDisbursementReadinessService $readiness,
    ): RedirectResponse {
        $customer = $this->customerOrFail();
        $member = $signatures->memberForCustomer($application, $customer);

        abort_unless($member, 404);
        abort_if($member->isLeader(), 403);
        abort_if($member->contract_signature_status === 'signed', 422);

        abort_unless($readiness->loanContract($application), 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $signatures->recordDecline($member, $customer, $data['reason'] ?? null);

        $leader = $application->customer;
        if ($leader) {
            app(\App\Services\GroupLoanNotificationService::class)->notifyLeaderContractDeclined(
                $leader,
                $application,
                $customer->full_name,
            );
        }

        $this->auditBorrower('group_contract.declined', $application, [
            'member_id' => $member->id,
            'reason'    => $data['reason'] ?? null,
        ]);

        return redirect()
            ->route('site.borrower.dashboard')
            ->with('status', __('borrower.apply.group.contract_declined'));
    }

    private function customerOrFail(): \App\Models\Customer
    {
        $customer = auth()->user()?->customer
            ?? \App\Models\Customer::where('user_id', auth()->id())->first();

        abort_unless($customer, 403);

        return $customer;
    }
}
