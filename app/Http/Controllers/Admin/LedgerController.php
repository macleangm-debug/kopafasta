<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitalWithdrawalRequest;
use App\Models\CustomerPayment;
use App\Models\MembershipHistory;
use App\Models\PartnerPayment;
use App\Models\PartnerSettlement;
use App\Models\Repayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedgerController extends Controller
{
    /** Inbound money: fees, repayments, membership — filterable ledger hub. */
    public function payments(Request $request): View
    {
        $tab = $request->string('tab', 'all')->toString();
        $status = $request->string('status', 'all')->toString();
        $type = $request->string('type')->toString();

        $types = config('payment_types.types', []);

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

        $counts = [
            'all' => CustomerPayment::query()->count(),
            'pending' => CustomerPayment::pending()->count(),
            'membership_pending' => MembershipHistory::query()->pending()->count(),
            'repayments_pending' => Repayment::query()->whereNull('approved_at')->where('status', 'pending')->count(),
        ];

        return view('admin.ledgers.payments', [
            'payments' => $payments,
            'tab'      => $tab,
            'status'   => $status,
            'type'     => $type,
            'types'    => $types,
            'counts'   => $counts,
        ]);
    }

    /** Outbound money: partner / supplier / capital partner payouts. */
    public function payouts(Request $request): View
    {
        $tab = $request->string('tab', 'partners')->toString();
        $status = $request->string('status')->toString();

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

        return view('admin.ledgers.payouts', [
            'tab'                => $tab,
            'status'             => $status,
            'statuses'           => ['pending', 'approved', 'paid', 'cancelled'],
            'partnerPayments'    => $partnerPayments,
            'capitalWithdrawals' => $capitalWithdrawals,
            'settlements'        => $settlements,
            'counts'             => [
                'partners_pending' => PartnerPayment::query()->where('status', 'pending')->count(),
                'capital_pending'  => CapitalWithdrawalRequest::query()->where('status', 'pending')->count(),
                'settlements'      => PartnerSettlement::query()->count(),
            ],
        ]);
    }
}
