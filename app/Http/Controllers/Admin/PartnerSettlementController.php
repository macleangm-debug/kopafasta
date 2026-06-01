<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerSettlement;
use App\Models\VendorPayment;
use App\Services\PartnerSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerSettlementController extends Controller
{
    public function index(): View
    {
        $settlements = PartnerSettlement::query()
            ->with('vendor')
            ->latest()
            ->paginate(20);

        return view('admin.partner-settlements.index', compact('settlements'));
    }

    public function show(PartnerSettlement $partnerSettlement): View
    {
        $partnerSettlement->load(['vendor', 'payments.task', 'approvedByUser']);

        return view('admin.partner-settlements.show', compact('partnerSettlement'));
    }

    public function approve(PartnerSettlement $partnerSettlement, PartnerSettlementService $service): RedirectResponse
    {
        $service->approveSettlement($partnerSettlement, auth()->user());

        return back()->with('status', 'Settlement batch approved.');
    }

    public function markPaid(Request $request, PartnerSettlement $partnerSettlement, PartnerSettlementService $service): RedirectResponse
    {
        $data = $request->validate([
            'channel'   => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:60'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $service->markSettlementPaid(
            $partnerSettlement,
            auth()->user(),
            $data['channel'] ?? null,
            $data['reference'] ?? null,
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Settlement marked as paid.');
    }
}
