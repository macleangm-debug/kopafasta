<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipHistory;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipPaymentController extends Controller
{
    public function index(): View
    {
        $pending = MembershipHistory::query()
            ->pending()
            ->with(['customer', 'actor'])
            ->latest()
            ->paginate(25);

        $recent = MembershipHistory::query()
            ->whereIn('event', ['payment_approved', 'payment_rejected'])
            ->with(['customer', 'actor'])
            ->latest()
            ->limit(15)
            ->get();

        return view('admin.membership-payments.index', compact('pending', 'recent'));
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
