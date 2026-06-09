<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanTopUpRequest;
use App\Services\LoanRequestReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanTopUpRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', LoanTopUpRequest::class);

        $status = $request->query('status', 'pending');

        $query = LoanTopUpRequest::query()
            ->with(['loan.customer', 'loan.product', 'customer'])
            ->latest();

        if ($status === 'pending') {
            $query->where('status', 'pending');
        } elseif ($status === 'approved') {
            $query->where('status', 'approved')->whereNull('disbursed_at');
        } elseif ($status === 'disbursed') {
            $query->where('status', 'disbursed');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->paginate(25)->withQueryString();

        $counts = [
            'pending'   => LoanTopUpRequest::where('status', 'pending')->count(),
            'approved'  => LoanTopUpRequest::where('status', 'approved')->whereNull('disbursed_at')->count(),
            'disbursed' => LoanTopUpRequest::where('status', 'disbursed')->count(),
            'rejected'  => LoanTopUpRequest::where('status', 'rejected')->count(),
        ];

        return view('admin.top-up-requests.index', compact('requests', 'status', 'counts'));
    }

    public function show(LoanTopUpRequest $topUpRequest): View
    {
        $this->authorize('view', $topUpRequest);

        $topUpRequest->load(['loan.customer', 'loan.product', 'customer', 'reviewedBy']);

        return view('admin.top-up-requests.show', [
            'record' => $topUpRequest,
        ]);
    }

    public function approve(Request $request, LoanTopUpRequest $topUpRequest, LoanRequestReviewService $service): RedirectResponse
    {
        $this->authorize('approve', $topUpRequest);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $service->approveTopUp($topUpRequest, $request->user(), $data['notes'] ?? null);

            return back()->with('status', 'Top-up request approved.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, LoanTopUpRequest $topUpRequest, LoanRequestReviewService $service): RedirectResponse
    {
        $this->authorize('update', $topUpRequest);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $service->rejectTopUp($topUpRequest, $request->user(), $data['notes'] ?? null);

            return back()->with('status', 'Top-up request rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function disburse(Request $request, LoanTopUpRequest $topUpRequest, LoanRequestReviewService $service): RedirectResponse
    {
        $this->authorize('disburse', $topUpRequest);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $service->disburseTopUp($topUpRequest, $request->user(), $data['notes'] ?? null);

            return back()->with('status', 'Top-up disbursed to loan.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
