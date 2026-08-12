<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitalWithdrawalRequest;
use App\Models\CustomerPayment;
use App\Models\Disbursement;
use App\Models\MembershipHistory;
use App\Models\PartnerPayment;
use App\Models\PartnerSettlement;
use App\Models\Repayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedgerController extends Controller
{
    /** Unified money ledger: inbound (direction=in) and outbound (direction=out). */
    public function payments(Request $request): View
    {
        $direction = $request->string('direction', 'in')->toString();
        if (! in_array($direction, ['in', 'out'], true)) {
            $direction = 'in';
        }

        $defaultTab = $direction === 'out' ? 'partners' : 'all';
        $tab = $request->string('tab', $defaultTab)->toString();
        $status = $request->string('status', $direction === 'in' ? 'all' : '')->toString();
        $type = $request->string('type')->toString();

        $types = config('payment_types.types', []);

        $inboundTabs = ['all', 'fees', 'repayments', 'membership', 'repayment_queue'];
        $outboundTabs = ['partners', 'capital', 'settlements', 'disbursements'];

        if ($direction === 'in' && ! in_array($tab, $inboundTabs, true)) {
            $tab = 'all';
        }
        if ($direction === 'out' && ! in_array($tab, $outboundTabs, true)) {
            $tab = 'partners';
        }

        $payments = null;
        $pendingMembership = null;
        $recentMembership = null;
        $partnerPayments = null;
        $capitalWithdrawals = null;
        $settlements = null;
        $disbursements = null;

        if ($direction === 'in' && in_array($tab, ['all', 'fees', 'repayments'], true)) {
            $query = CustomerPayment::query()
                ->with(['customer', 'loan', 'verifier', 'journalEntry'])
                ->latest();

            if ($status !== '' && $status !== 'all') {
                if ($status === 'pending') {
                    $query->pending();
                } else {
                    $query->where('status', $status);
                }
            }

            if ($tab === 'fees') {
                $query->whereIn('payment_type', array_keys(array_diff_key($types, array_flip(['loan_repayment', 'penalty_payment']))));
            } elseif ($tab === 'repayments') {
                $query->whereIn('payment_type', ['loan_repayment', 'penalty_payment']);
            } elseif ($type !== '') {
                $query->where('payment_type', $type);
            }

            $payments = $query->paginate(25)->withQueryString();
        }

        if ($direction === 'in' && $tab === 'membership') {
            $pendingMembership = MembershipHistory::query()
                ->pending()
                ->with(['customer', 'actor'])
                ->latest()
                ->paginate(25)
                ->withQueryString();

            $recentMembership = MembershipHistory::query()
                ->whereIn('event', ['payment_approved', 'payment_rejected'])
                ->with(['customer', 'actor'])
                ->latest()
                ->limit(15)
                ->get();
        }

        if ($direction === 'out') {
            $partnerPayments = PartnerPayment::query()
                ->with(['partner', 'task', 'partnerSettlement'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(25, ['*'], 'partner_page')
                ->withQueryString();

            $capitalWithdrawals = CapitalWithdrawalRequest::query()
                ->with(['lender'])
                ->latest()
                ->paginate(25, ['*'], 'capital_page')
                ->withQueryString();

            $settlements = PartnerSettlement::query()
                ->latest()
                ->paginate(15, ['*'], 'settlement_page')
                ->withQueryString();

            $disbursements = Disbursement::query()
                ->with('loan.customer')
                ->latest()
                ->paginate(25, ['*'], 'disbursement_page')
                ->withQueryString();
        }

        $inCount = CustomerPayment::query()->count();
        $inAmount = (float) CustomerPayment::query()->sum('amount');
        $outPartnerAmount = (float) PartnerPayment::query()->sum('amount');
        $outCapitalAmount = (float) CapitalWithdrawalRequest::query()->sum('amount');
        $outDisbursementAmount = (float) Disbursement::query()->sum('amount');
        $outCount = PartnerPayment::query()->count()
            + CapitalWithdrawalRequest::query()->count()
            + PartnerSettlement::query()->count()
            + Disbursement::query()->count();

        $counts = [
            'in_count' => $inCount,
            'in_amount' => $inAmount,
            'out_count' => $outCount,
            'out_amount' => $outPartnerAmount + $outCapitalAmount + $outDisbursementAmount,
            'all' => $inCount,
            'pending' => CustomerPayment::pending()->count(),
            'membership_pending' => MembershipHistory::query()->pending()->count(),
            'repayments_pending' => Repayment::query()->whereNull('approved_at')->where('status', 'pending')->count(),
            'partners_pending' => PartnerPayment::query()->where('status', 'pending')->count(),
            'capital_pending' => CapitalWithdrawalRequest::query()->where('status', 'pending')->count(),
            'settlements' => PartnerSettlement::query()->count(),
            'disbursements' => Disbursement::query()->count(),
        ];

        return view('admin.ledgers.payments', [
            'direction'          => $direction,
            'payments'           => $payments,
            'pendingMembership'  => $pendingMembership,
            'recentMembership'   => $recentMembership,
            'partnerPayments'    => $partnerPayments,
            'capitalWithdrawals' => $capitalWithdrawals,
            'settlements'        => $settlements,
            'disbursements'      => $disbursements,
            'tab'                => $tab,
            'status'             => $status,
            'type'               => $type,
            'types'              => $types,
            'statuses'           => ['pending', 'approved', 'paid', 'cancelled'],
            'counts'             => $counts,
        ]);
    }

    /** Legacy payouts URL — redirect into the unified ledger (outbound). */
    public function payouts(Request $request): RedirectResponse
    {
        return redirect()->route('admin.payments.ledger', array_filter([
            'direction' => 'out',
            'tab' => $request->string('tab')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ]));
    }
}
