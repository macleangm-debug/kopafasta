<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\Vendor;
use App\Services\ValuationPartnerService;
use App\Services\PostApprovalFeeService;
use App\Services\GpsPartnerService;
use App\Models\LoanApplicationPostApprovalFee;
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

    public function autoAssign(): View
    {
        $boards = app(\App\Services\PartnerAutoAssignOverviewService::class)->originationBoards();

        return view('admin.partners.origination-auto-assign', compact('boards'));
    }

    public function saveAutoAssign(Request $request): RedirectResponse
    {
        app(\App\Services\PartnerAutoAssignPolicy::class)->saveOriginationFromRequest($request->all());

        return redirect()
            ->route('admin.partners.origination-auto-assign')
            ->with('status', 'Origination auto-assignment saved.');
    }

    public function assignValuer(Request $request, LoanApplication $loanApplication, ValuationPartnerService $service): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['nullable', 'exists:partners,id'],
            'auto'      => ['nullable', 'boolean'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $valuer = filled($data['vendor_id'] ?? null)
            ? Vendor::findOrFail($data['vendor_id'])
            : ($request->boolean('auto') ? $service->suggestValuer($loanApplication) : null);

        if (! $valuer) {
            return back()->with('error', 'No matching valuer found for the borrower region. Assign manually or update partner regions.');
        }

        $service->assign($loanApplication, $valuer, $request->user(), $data['notes'] ?? null);

        return back()->with('status', 'Valuation partner assigned: '.$valuer->name.'.');
    }

    public function updateCollateralUwStatus(
        Request $request,
        LoanApplication $loanApplication,
        \App\Models\LoanApplicationAsset $asset,
        \App\Services\AssetBackedApplyService $assetApply,
    ): RedirectResponse {
        abort_unless($asset->loan_application_id === $loanApplication->id, 404);

        $data = $request->validate([
            'uw_status' => ['required', 'in:pending,accepted,declined'],
            'uw_notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $assetApply->setUnderwritingStatus($asset, $data['uw_status'], $data['uw_notes'] ?? null);

        $label = match ($data['uw_status']) {
            'accepted' => 'accepted',
            'declined' => 'declined',
            default    => 'reset to pending',
        };

        return back()->with('status', 'Collateral '.$label.'.');
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

    public function updatePostApprovalFee(Request $request, LoanApplication $loanApplication, LoanApplicationPostApprovalFee $fee, PostApprovalFeeService $fees): RedirectResponse
    {
        abort_unless($fee->loan_application_id === $loanApplication->id, 404);

        $data = $request->validate([
            'action' => ['required', 'in:update,waive'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'waive') {
            $fees->waiveApplicationFee($fee, $request->user(), $data['reason'] ?? null);

            return back()->with('status', 'Post-approval fee waived.');
        }

        $fees->updateApplicationFee(
            $fee,
            isset($data['amount']) ? (float) $data['amount'] : null,
            $data['reason'] ?? null,
            $request->user(),
        );

        return back()->with('status', 'Post-approval fee updated.');
    }

    public function assignGpsInstaller(Request $request, LoanApplication $loanApplication, GpsPartnerService $service): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['nullable', 'exists:partners,id'],
            'auto'      => ['nullable', 'boolean'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $installer = filled($data['vendor_id'] ?? null)
            ? \App\Models\Vendor::findOrFail($data['vendor_id'])
            : ($request->boolean('auto') ? $service->suggestInstaller($loanApplication) : null);

        if (! $installer) {
            return back()->with('error', 'No matching GPS installer found for the borrower region.');
        }

        $service->assign($loanApplication, $installer, $request->user(), $data['notes'] ?? null);

        return back()->with('status', 'GPS installer assigned: '.$installer->name.'.');
    }
}
