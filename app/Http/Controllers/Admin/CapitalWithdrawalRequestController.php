<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitalWithdrawalRequest;
use App\Services\CapitalPartnerCapitalService;
use Illuminate\Http\Request;

class CapitalWithdrawalRequestController extends Controller
{
    public function approve(Request $request, CapitalWithdrawalRequest $capital_withdrawal_request, CapitalPartnerCapitalService $capital)
    {
        $data = $request->validate(['admin_notes' => ['nullable', 'string', 'max:2000']]);
        $capital->approveWithdrawal($capital_withdrawal_request, $data['admin_notes'] ?? null, $request->user());

        return back()->with('status', 'Withdrawal approved and recorded in the investor ledger.');
    }

    public function reject(Request $request, CapitalWithdrawalRequest $capital_withdrawal_request, CapitalPartnerCapitalService $capital)
    {
        $data = $request->validate(['admin_notes' => ['nullable', 'string', 'max:2000']]);
        $capital->rejectWithdrawal($capital_withdrawal_request, $data['admin_notes'] ?? null, $request->user());

        return back()->with('status', 'Withdrawal request rejected.');
    }
}
