<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\ArrearCase;
use App\Models\User;
use App\Services\ActiveLoanServicingService;
use App\Services\AuctionProceedsService;
use App\Services\LoanCollectionActionService;
use App\Services\RecoveryAssignmentService;
use App\Services\RecoveryPartnerService;
use App\Services\WriteOffRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArrearCaseController extends Controller
{
    use AuditsActions;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ArrearCase::class);

        $status = $request->query('status', 'open');

        $query = ArrearCase::query()
            ->with(['loan.customer', 'loan.product', 'assignee'])
            ->latest('last_follow_up_at')
            ->latest('id');

        if ($status === 'open') {
            $query->where('status', 'open');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $cases = $query->paginate(25)->withQueryString();

        $counts = [
            'open'     => ArrearCase::where('status', 'open')->count(),
            'resolved' => ArrearCase::where('status', 'resolved')->count(),
            'escalated'=> ArrearCase::where('status', 'escalated')->count(),
        ];

        $totals = [
            'amount_in_arrears' => (float) ArrearCase::where('status', 'open')->sum('amount_in_arrears'),
            'penalties'         => (float) ArrearCase::where('status', 'open')->sum('penalty_amount'),
        ];

        return view('admin.arrear-cases.index', compact('cases', 'status', 'counts', 'totals'));
    }

    public function show(ArrearCase $arrearCase): View
    {
        $this->authorize('view', $arrearCase);

        $arrearCase->load([
            'loan.customer',
            'loan.product',
            'loan.assetAuctionSettlements' => fn ($q) => $q->latest('settled_at'),
            'loan.repaymentSchedules' => fn ($q) => $q->orderBy('installment_no'),
            'assignee',
            'actions' => fn ($q) => $q->with('performer')->latest('performed_at'),
            'recoveryAssignments.vendor',
        ]);

        $servicing = $arrearCase->loan
            ? app(ActiveLoanServicingService::class)->forLoan($arrearCase->loan)
            : null;

        $collectors = User::query()
            ->whereIn('role', ['collector', 'officer', 'manager', 'admin', 'super_admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $writeOffService = app(WriteOffRequestService::class);
        $approvalRequired = (bool) \App\Models\Setting::get('finance.write_off_approval_required');
        $canRecommendWriteOff = $arrearCase->loan
            && $writeOffService->canRecommend(auth()->user())
            && ! $writeOffService->hasOpenRequest($arrearCase->loan);

        $recoveryPartners = app(RecoveryPartnerService::class)
            ->filteredQuery()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.arrear-cases.show', array_merge(
            compact(
                'arrearCase',
                'servicing',
                'collectors',
                'writeOffService',
                'approvalRequired',
                'canRecommendWriteOff',
                'recoveryPartners',
            ),
            [
                'recoveryAssignments'  => $arrearCase->recoveryAssignments,
                'recoveryPartnerTypes' => app(RecoveryPartnerService::class)->partnerTypeOptions(),
            ],
        ));
    }

    public function update(Request $request, ArrearCase $arrearCase): RedirectResponse
    {
        $this->authorize('update', $arrearCase);

        $data = $request->validate([
            'status'            => ['sometimes', 'string', 'in:open,resolved,escalated'],
            'assigned_to'       => ['nullable', 'exists:users,id'],
            'amount_in_arrears' => ['sometimes', 'numeric', 'min:0'],
            'penalty_amount'    => ['sometimes', 'numeric', 'min:0'],
            'days_past_due'     => ['sometimes', 'integer', 'min:0'],
        ]);

        $arrearCase->update($data);

        $this->auditAdmin('admin.arrear_cases.updated', $arrearCase->loan, [
            'arrear_case_id' => $arrearCase->id,
            'changes'        => array_keys($data),
        ]);

        return back()->with('status', 'Collection case updated.');
    }

    public function addAction(
        Request $request,
        ArrearCase $arrearCase,
        LoanCollectionActionService $service,
    ): RedirectResponse {
        $this->authorize('addAction', $arrearCase);

        $data = $request->validate([
            'action_type' => ['required', 'string', 'max:100'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'result'      => ['nullable', 'string', 'max:100'],
        ]);

        $service->logForCase(
            $arrearCase,
            $request->user(),
            $data['action_type'],
            $data['notes'] ?? null,
            $data['result'] ?? null,
        );

        $this->auditAdmin('admin.arrear_cases.collection_action', $arrearCase->loan, [
            'arrear_case_id' => $arrearCase->id,
            'action_type'    => $data['action_type'],
        ]);

        return back()->with('status', 'Collection action logged.');
    }

    public function assignRecoveryPartner(
        Request $request,
        ArrearCase $arrearCase,
        RecoveryAssignmentService $service,
    ): RedirectResponse {
        $this->authorize('update', $arrearCase);

        $data = $request->validate([
            'vendor_id'    => ['required', 'exists:partners,id'],
            'partner_type' => ['required', 'string', 'in:call_center,debt_collector,auctioneer,legal_partner,gps_partner'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $vendor = \App\Models\Vendor::findOrFail($data['vendor_id']);

        $assignment = $service->assign(
            $arrearCase,
            $vendor,
            $data['partner_type'],
            $request->user(),
            $data['notes'] ?? null,
        );

        $this->auditAdmin('admin.arrear_cases.recovery_assigned', $arrearCase->loan, [
            'arrear_case_id' => $arrearCase->id,
            'assignment_id'  => $assignment->id,
            'vendor_id'      => $vendor->id,
            'partner_type'   => $data['partner_type'],
        ]);

        return back()->with('status', 'Recovery partner assigned.');
    }

    public function recordAuctionSettlement(
        Request $request,
        ArrearCase $arrearCase,
        AuctionProceedsService $auctions,
    ): RedirectResponse {
        $this->authorize('update', $arrearCase);

        $loan = $arrearCase->loan;
        abort_unless($loan, 404);

        $data = $request->validate([
            'auction_proceeds' => ['required', 'numeric', 'min:0.01'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $settlement = $auctions->settle(
            $loan,
            (float) $data['auction_proceeds'],
            $request->user(),
            $arrearCase,
            null,
            $data['notes'] ?? null,
        );

        $this->auditAdmin('admin.arrear_cases.auction_settled', $loan, [
            'arrear_case_id'  => $arrearCase->id,
            'settlement_id'   => $settlement->id,
            'auction_proceeds'=> $data['auction_proceeds'],
            'borrower_refund' => $settlement->borrower_refund,
            'remaining_balance' => $settlement->remaining_balance,
        ]);

        $message = $settlement->loan_closed
            ? 'Auction settled and loan closed.'
            : 'Auction settled. Remaining balance: '.format_money((float) $settlement->remaining_balance);

        if ((float) $settlement->borrower_refund > 0) {
            $message .= ' Borrower refund due: '.format_money((float) $settlement->borrower_refund).'.';
        }

        return back()->with('status', $message);
    }
}
