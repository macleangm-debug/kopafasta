<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipHistory;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MembershipPaymentController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.payments.ledger', [
            'direction' => 'in',
            'tab' => 'membership',
        ]);
    }

    public function approve(MembershipHistory $membershipHistory, MembershipService $service): RedirectResponse
    {
        try {
            $customer = $service->approvePendingPayment($membershipHistory, auth()->id());
            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));

            return back()->with('status', "Payment approved. Membership activated for {$name}.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, MembershipHistory $membershipHistory, MembershipService $service): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->rejectPendingPayment($membershipHistory, auth()->id(), $data['notes'] ?? null);

            return back()->with('status', 'Payment rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
