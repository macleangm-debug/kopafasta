<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorPayment;
use App\Services\PartnerSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $payments = VendorPayment::query()
            ->with(['vendor', 'task', 'partnerSettlement'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.vendor-payments.index', [
            'payments' => $payments,
            'status'   => $status,
            'statuses' => ['pending', 'approved', 'paid', 'cancelled'],
        ]);
    }

    public function approve(VendorPayment $vendorPayment, PartnerSettlementService $service): RedirectResponse
    {
        $service->approvePayment($vendorPayment, auth()->user());

        return back()->with('status', 'Payment approved for settlement.');
    }

    public function cancel(Request $request, VendorPayment $vendorPayment, PartnerSettlementService $service): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $service->cancelPayment($vendorPayment, $data['notes'] ?? null);

        return back()->with('status', 'Payment cancelled.');
    }
}
