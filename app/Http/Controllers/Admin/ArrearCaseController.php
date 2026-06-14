<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\ArrearCase;
use App\Models\User;
use App\Services\ActiveLoanServicingService;
use App\Services\LoanCollectionActionService;
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
            'loan.repaymentSchedules' => fn ($q) => $q->orderBy('installment_no'),
            'assignee',
            'actions' => fn ($q) => $q->with('performer')->latest('performed_at'),
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

        return view('admin.arrear-cases.show', compact(
            'arrearCase',
            'servicing',
            'collectors',
            'writeOffService',
            'approvalRequired',
            'canRecommendWriteOff',
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
}
