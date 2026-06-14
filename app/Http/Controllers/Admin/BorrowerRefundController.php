<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BorrowerRefund;
use App\Services\BorrowerRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BorrowerRefundController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'awaiting_payout';

        $query = BorrowerRefund::query()
            ->with(['customer', 'loan', 'settlement'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $refunds = $query->paginate(25)->withQueryString();

        $counts = [
            'pending'          => BorrowerRefund::where('status', BorrowerRefund::STATUS_PENDING)->count(),
            'awaiting_payout'  => BorrowerRefund::where('status', BorrowerRefund::STATUS_AWAITING_PAYOUT)->count(),
            'paid'             => BorrowerRefund::where('status', BorrowerRefund::STATUS_PAID)->count(),
        ];

        return view('admin.borrower-refunds.index', compact('refunds', 'status', 'counts'));
    }

    public function show(BorrowerRefund $borrowerRefund): View
    {
        $borrowerRefund->load(['customer', 'loan', 'settlement', 'payer', 'accrualJournalEntry', 'payoutJournalEntry', 'disbursementMobileMoneyAccount']);
        $disbursementDummy = app(\App\Services\MobileMoneyDisbursementService::class)->usesDummyGateway();

        return view('admin.borrower-refunds.show', compact('borrowerRefund', 'disbursementDummy'));
    }

    public function markPaid(Request $request, BorrowerRefund $borrowerRefund, BorrowerRefundService $service): RedirectResponse
    {
        $data = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:80', 'required_unless:auto_disburse,1'],
            'notes'             => ['nullable', 'string', 'max:500'],
            'auto_disburse'     => ['nullable', 'boolean'],
        ]);

        $service->markPaid(
            $borrowerRefund,
            $request->user(),
            $data['payment_reference'] ?? null,
            $data['notes'] ?? null,
            $request->boolean('auto_disburse'),
        );

        return redirect()
            ->route('admin.borrower-refunds.show', $borrowerRefund)
            ->with('status', 'Refund marked as paid.');
    }
}
