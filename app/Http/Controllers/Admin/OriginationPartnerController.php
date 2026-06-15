<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\Vendor;
use App\Services\ValuationPartnerService;
use App\Services\PostApprovalFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OriginationPartnerController extends Controller
{
    public function valuationIndex(): View
    {
        $valuers = Vendor::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('category', 'valuer')->orWhere('roles', 'like', '%"valuer"%');
            })
            ->orderBy('name')
            ->get();

        return view('admin.origination.valuation-partners', compact('valuers'));
    }

    public function assignValuer(Request $request, LoanApplication $loanApplication, ValuationPartnerService $service): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $valuer = Vendor::findOrFail($data['vendor_id']);
        $service->assign($loanApplication, $valuer, $request->user(), $data['notes'] ?? null);

        return back()->with('status', 'Valuation partner assigned.');
    }

    public function addManualFee(Request $request, LoanApplication $loanApplication, PostApprovalFeeService $fees): RedirectResponse
    {
        $data = $request->validate([
            'description'   => ['required', 'string', 'max:200'],
            'partner_cost'  => ['required', 'numeric', 'min:0.01'],
            'markup_percent'=> ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $fees->addManualFee(
            $loanApplication,
            $data['description'],
            (float) $data['partner_cost'],
            (float) $data['markup_percent'],
            $request->user()->id,
        );

        return back()->with('status', 'Manual post-approval fee added. Borrower will receive a payment request.');
    }
}
