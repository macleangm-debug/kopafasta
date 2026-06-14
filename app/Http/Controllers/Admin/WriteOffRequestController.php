<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\ArrearCase;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\WriteOffRequest;
use App\Services\WriteOffRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WriteOffRequestController extends Controller
{
    use AuditsActions;

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasPermission('loans.view'), 403);

        $status = $request->query('status', 'pending');

        $query = WriteOffRequest::query()
            ->with(['loan.customer', 'loan.product', 'recommender', 'managerApprover', 'financeApprover', 'rule'])
            ->latest('id');

        if ($status === 'pending') {
            $query->whereIn('status', [
                WriteOffRequest::STATUS_RECOMMENDED,
                WriteOffRequest::STATUS_MANAGER_APPROVED,
            ]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->paginate(25)->withQueryString();
        $service = app(WriteOffRequestService::class);

        $counts = [
            'pending'   => WriteOffRequest::whereIn('status', [
                WriteOffRequest::STATUS_RECOMMENDED,
                WriteOffRequest::STATUS_MANAGER_APPROVED,
            ])->count(),
            'recommended' => WriteOffRequest::where('status', WriteOffRequest::STATUS_RECOMMENDED)->count(),
            'manager_approved' => WriteOffRequest::where('status', WriteOffRequest::STATUS_MANAGER_APPROVED)->count(),
            'completed' => WriteOffRequest::where('status', WriteOffRequest::STATUS_COMPLETED)->count(),
            'rejected'  => WriteOffRequest::where('status', WriteOffRequest::STATUS_REJECTED)->count(),
        ];

        return view('admin.write-off-requests.index', compact('requests', 'status', 'counts', 'service'));
    }

    public function show(WriteOffRequest $writeOffRequest): View
    {
        abort_unless(auth()->user()?->hasPermission('loans.view'), 403);

        $writeOffRequest->load([
            'loan.customer',
            'loan.product',
            'arrearCase',
            'rule',
            'recommender',
            'managerApprover',
            'financeApprover',
        ]);

        $service = app(WriteOffRequestService::class);
        $approvalRequired = (bool) Setting::get('finance.write_off_approval_required');

        return view('admin.write-off-requests.show', compact('writeOffRequest', 'service', 'approvalRequired'));
    }

    public function recommendFromLoan(Request $request, Loan $loan, WriteOffRequestService $service): RedirectResponse
    {
        $service->assertCanRecommend($request->user());

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $writeOffRequest = $service->recommend(
            $loan,
            $request->user(),
            $data['reason'],
            isset($data['amount']) ? (float) $data['amount'] : null,
        );

        $this->auditAdmin('admin.write_off_requests.recommended', $loan, [
            'write_off_request_id' => $writeOffRequest->id,
            'amount'               => $writeOffRequest->amount,
        ]);

        return redirect()
            ->route('admin.write-off-requests.show', $writeOffRequest)
            ->with('status', 'Write-off recommended. Awaiting manager approval.');
    }

    public function recommendFromCase(Request $request, ArrearCase $arrearCase, WriteOffRequestService $service): RedirectResponse
    {
        $service->assertCanRecommend($request->user());
        abort_unless($arrearCase->loan, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $writeOffRequest = $service->recommend(
            $arrearCase->loan,
            $request->user(),
            $data['reason'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            $arrearCase,
        );

        $this->auditAdmin('admin.write_off_requests.recommended', $arrearCase->loan, [
            'write_off_request_id' => $writeOffRequest->id,
            'arrear_case_id'       => $arrearCase->id,
        ]);

        return redirect()
            ->route('admin.write-off-requests.show', $writeOffRequest)
            ->with('status', 'Write-off recommended from collection case.');
    }

    public function managerApprove(Request $request, WriteOffRequest $writeOffRequest, WriteOffRequestService $service): RedirectResponse
    {
        $data = $request->validate([
            'manager_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $writeOffRequest = $service->managerApprove(
            $writeOffRequest,
            $request->user(),
            $data['manager_notes'] ?? null,
        );

        $this->auditAdmin('admin.write_off_requests.manager_approved', $writeOffRequest->loan, [
            'write_off_request_id' => $writeOffRequest->id,
        ]);

        return back()->with('status', 'Manager approval recorded. Awaiting finance execution.');
    }

    public function financeApprove(Request $request, WriteOffRequest $writeOffRequest, WriteOffRequestService $service): RedirectResponse
    {
        $data = $request->validate([
            'finance_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $writeOffRequest = $service->financeApproveAndExecute(
            $writeOffRequest,
            $request->user(),
            $data['finance_notes'] ?? null,
        );

        $this->auditAdmin('admin.write_off_requests.executed', $writeOffRequest->loan, [
            'write_off_request_id' => $writeOffRequest->id,
            'amount'               => $writeOffRequest->amount,
        ]);

        return redirect()
            ->route('admin.loans.show', $writeOffRequest->loan)
            ->with('status', 'Write-off executed and loan marked written off.');
    }

    public function reject(Request $request, WriteOffRequest $writeOffRequest, WriteOffRequestService $service): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $writeOffRequest = $service->reject($writeOffRequest, $request->user(), $data['rejection_reason']);

        $this->auditAdmin('admin.write_off_requests.rejected', $writeOffRequest->loan, [
            'write_off_request_id' => $writeOffRequest->id,
        ]);

        return back()->with('status', 'Write-off request rejected.');
    }
}
