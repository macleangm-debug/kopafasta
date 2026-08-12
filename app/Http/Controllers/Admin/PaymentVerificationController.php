<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $type = $request->query('type', '');

        $query = CustomerPayment::query()
            ->with(['customer', 'bankAccount', 'verifier', 'loan'])
            ->latest();

        if ($status === 'pending') {
            $query->pending();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($type !== '') {
            $query->where('payment_type', $type);
        }

        $payments = $query->paginate(25)->withQueryString();

        $counts = [
            'pending' => CustomerPayment::pending()->count(),
            'verified' => CustomerPayment::where('status', 'verified')->count(),
            'rejected' => CustomerPayment::where('status', 'rejected')->count(),
            'verified_today' => CustomerPayment::query()
                ->where('status', 'verified')
                ->whereDate('verified_at', today())
                ->count(),
            'missing_journal' => CustomerPayment::query()
                ->whereIn('status', ['verified', 'paid'])
                ->whereNull('journal_entry_id')
                ->count(),
        ];

        $types = config('payment_types.types', []);

        return view('admin.payments.index', compact('payments', 'status', 'counts', 'type', 'types'));
    }

    public function show(CustomerPayment $payment): View
    {
        $payment->load([
            'customer',
            'bankAccount',
            'mobileMoneyAccount',
            'loan',
            'loanProduct',
            'journalEntry',
            'verifier',
            'source',
        ]);

        try {
            $fundDestinations = app(\App\Services\PaymentFundDestinationService::class)->destinations($payment);
        } catch (\Throwable $e) {
            report($e);
            $fundDestinations = [];
        }

        return view('admin.payments.show', compact('payment', 'fundDestinations'));
    }

    public function verify(CustomerPayment $payment, CustomerPaymentService $service): RedirectResponse
    {
        try {
            $service->verify($payment, auth()->id());

            return back()->with('status', "Payment {$payment->reference} verified and ledger posted.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, CustomerPayment $payment, CustomerPaymentService $service): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $service->reject($payment, auth()->id(), $data['notes'] ?? null);

            return back()->with('status', "Payment {$payment->reference} rejected.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function requestClarification(Request $request, CustomerPayment $payment, CustomerPaymentService $service): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        try {
            $service->requestClarification($payment, auth()->id(), $data['notes']);

            return back()->with('status', "Clarification requested for {$payment->reference}.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function proof(CustomerPayment $payment): StreamedResponse
    {
        abort_unless($payment->hasProof(), 404);

        return Storage::disk('public')->download($payment->proof_path, $payment->proof_original_name ?? 'proof');
    }
}
