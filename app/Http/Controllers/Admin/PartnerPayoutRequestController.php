<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerPayoutRequest;
use App\Services\PartnerPayoutRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerPayoutRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        $requests = PartnerPayoutRequest::query()
            ->with(['partner', 'reviewedByUser'])
            ->when(
                in_array($status, ['pending', 'approved', 'rejected', 'paid'], true),
                fn ($q) => $q->where('status', $status)
            )
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.partner-payout-requests.index', [
            'requests' => $requests,
            'status'   => $status,
            'counts'   => [
                'pending'  => PartnerPayoutRequest::where('status', 'pending')->count(),
                'approved' => PartnerPayoutRequest::where('status', 'approved')->count(),
                'paid'     => PartnerPayoutRequest::where('status', 'paid')->count(),
                'rejected' => PartnerPayoutRequest::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function approve(PartnerPayoutRequest $partnerPayoutRequest, PartnerPayoutRequestService $payouts): RedirectResponse
    {
        $payouts->approve($partnerPayoutRequest, auth('admin')->user());

        return back()->with('status', 'Payout request approved.');
    }

    public function reject(Request $request, PartnerPayoutRequest $partnerPayoutRequest, PartnerPayoutRequestService $payouts): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $payouts->reject($partnerPayoutRequest, auth('admin')->user(), $data['reason'] ?? null);

        return back()->with('status', 'Payout request rejected.');
    }

    public function markPaid(PartnerPayoutRequest $partnerPayoutRequest, PartnerPayoutRequestService $payouts): RedirectResponse
    {
        $payouts->markPaid($partnerPayoutRequest, auth('admin')->user());

        return back()->with('status', 'Payout marked as paid.');
    }
}
