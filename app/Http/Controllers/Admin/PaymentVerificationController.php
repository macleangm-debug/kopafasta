<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use App\Services\MoneyMovementSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentVerificationController extends Controller
{
    public function index(Request $request, MoneyMovementSummaryService $moneySummary): View
    {
        // Collections desk = money in. Default list = complete only; bank queue is a separate tab.
        $status = $request->query('status', 'complete');
        if (! in_array($status, ['complete', 'awaiting_bank', 'rejected', 'all'], true)) {
            // Legacy links: pending → bank queue; verified → complete.
            $status = match ($status) {
                'pending', 'pending_verification', 'clarification_requested' => 'awaiting_bank',
                'verified', 'paid' => 'complete',
                default => 'complete',
            };
        }
        $type = $request->query('type', '');
        $q = trim((string) $request->query('q', ''));

        $query = CustomerPayment::query()
            ->with(['customer', 'bankAccount', 'verifier', 'loan', 'loanProduct', 'source'])
            ->latest();

        if ($status === 'complete') {
            $query->complete();
        } elseif ($status === 'awaiting_bank') {
            $query->awaitingBankVerification();
        } elseif ($status === 'rejected') {
            $query->where('status', 'rejected');
        }
        // 'all' = no status filter (ops / support)

        if ($type !== '') {
            $query->where('payment_type', $type);
        }

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term, $q) {
                $inner->where('reference', 'like', $term)
                    ->orWhere('payment_type', 'like', $term)
                    ->orWhere('payment_method', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('customer_number', 'like', $term)
                        ->orWhere('national_id', 'like', $term)
                        ->orWhere('region', 'like', $term));
                if (is_numeric(str_replace([',', ' '], '', $q))) {
                    $inner->orWhere('amount', (float) str_replace([',', ' '], '', $q));
                }
            });
        }

        $payments = $query->paginate(25)->withQueryString();

        $incomingComplete = $moneySummary->completeIncoming();
        $outgoingComplete = $moneySummary->completeOutgoing();

        $counts = [
            'complete' => $incomingComplete['count'],
            'complete_amount' => $incomingComplete['amount'],
            'outgoing_complete' => $outgoingComplete['count'],
            'outgoing_complete_amount' => $outgoingComplete['amount'],
            'awaiting_bank' => CustomerPayment::awaitingBankVerification()->count(),
            'rejected' => CustomerPayment::where('status', 'rejected')->count(),
            'verified_today' => CustomerPayment::query()
                ->where(function ($q) {
                    $q->where(function ($inner) {
                        $inner->where('status', 'verified')->whereDate('verified_at', today());
                    })->orWhere(function ($inner) {
                        $inner->where('status', 'paid')->whereDate('updated_at', today());
                    });
                })
                ->count(),
            'missing_journal' => CustomerPayment::query()
                ->complete()
                ->whereNull('journal_entry_id')
                ->count(),
        ];

        $types = config('payment_types.types', []);

        return view('admin.payments.index', compact('payments', 'status', 'counts', 'type', 'types', 'q'));
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

        if ($payment->source instanceof \App\Models\AssetReservation) {
            $payment->source->loadMissing(['asset.vendor', 'loanApplication.product']);
        } elseif ($payment->source instanceof \App\Models\LoanApplication) {
            $payment->source->loadMissing('product');
        }

        // Bank payments always show the configured collection bank (TCB).
        if ($payment->payment_method === 'bank_transfer' && ! $payment->bankAccount) {
            $collectionBank = app(\App\Services\PaymentAccountService::class)
                ->resolveBankAccount((string) $payment->payment_type, $payment->loanProduct);
            if ($collectionBank) {
                $payment->setRelation('bankAccount', $collectionBank);
                if (! $payment->bank_account_id) {
                    $payment->forceFill(['bank_account_id' => $collectionBank->id])->saveQuietly();
                }
            }
        }

        $paymentContext = $payment->adminContext();

        try {
            $fundDestinations = app(\App\Services\PaymentFundDestinationService::class)->destinations($payment);
        } catch (\Throwable $e) {
            report($e);
            $fundDestinations = [];
        }

        return view('admin.payments.show', compact('payment', 'fundDestinations', 'paymentContext'));
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
