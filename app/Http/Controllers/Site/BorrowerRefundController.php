<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BorrowerRefund;
use App\Services\BorrowerRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BorrowerRefundController extends Controller
{
    protected function customer()
    {
        return Auth::user()->customer ?? abort(404);
    }

    public function index(): View
    {
        $customer = $this->customer();
        $refunds = BorrowerRefund::query()
            ->with(['loan', 'settlement'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->get();

        return view('site.borrower.refunds', compact('customer', 'refunds'));
    }

    public function submitDetails(Request $request, BorrowerRefund $borrowerRefund, BorrowerRefundService $service): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless((int) $borrowerRefund->customer_id === (int) $customer->id, 404);

        $service->submitPayoutDetails($borrowerRefund, $customer, $request->all());

        return back()->with('status', 'Payout details submitted. We will process your refund shortly.');
    }
}
