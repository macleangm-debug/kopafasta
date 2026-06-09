<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestructureRequest;
use App\Services\LoanRequestReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestructureRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', RestructureRequest::class);

        $status = $request->query('status', 'pending');

        $query = RestructureRequest::query()
            ->with(['loan.customer', 'loan.product', 'customer'])
            ->latest();

        if ($status === 'pending') {
            $query->where('status', 'pending');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->paginate(25)->withQueryString();

        $counts = [
            'pending'  => RestructureRequest::where('status', 'pending')->count(),
            'approved' => RestructureRequest::where('status', 'approved')->count(),
            'rejected' => RestructureRequest::where('status', 'rejected')->count(),
        ];

        return view('admin.restructure-requests.index', compact('requests', 'status', 'counts'));
    }

    public function show(RestructureRequest $restructureRequest): View
    {
        $this->authorize('view', $restructureRequest);

        $restructureRequest->load(['loan.customer', 'loan.product', 'customer']);

        return view('admin.restructure-requests.show', [
            'record' => $restructureRequest,
        ]);
    }

    public function approve(Request $request, RestructureRequest $restructureRequest, LoanRequestReviewService $service): RedirectResponse
    {
        $this->authorize('approve', $restructureRequest);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $service->approveRestructure($restructureRequest, $request->user(), $data['notes'] ?? null);

            return back()->with('status', 'Restructure request approved.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, RestructureRequest $restructureRequest, LoanRequestReviewService $service): RedirectResponse
    {
        $this->authorize('update', $restructureRequest);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $service->rejectRestructure($restructureRequest, $request->user(), $data['notes'] ?? null);

            return back()->with('status', 'Restructure request rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
