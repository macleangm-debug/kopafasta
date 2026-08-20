<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\LoanApplication;
use App\Models\PartnerPayment;
use App\Models\VendorPayment;
use App\Services\PartnerSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $q = trim($request->string('q')->toString());

        $payments = VendorPayment::query()
            ->with(['vendor', 'task.loanApplication.customer', 'task.loan', 'partnerSettlement'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $this->applyPayoutSearch($query, $q))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.vendor-payments.index', [
            'payments' => $payments,
            'status'   => $status,
            'q'        => $q,
            'statuses' => ['pending', 'approved', 'paid', 'cancelled'],
        ]);
    }

    public function show(VendorPayment $vendorPayment): View
    {
        $vendorPayment->load([
            'vendor',
            'task.loanApplication.customer',
            'task.loanApplication.product',
            'task.loan.customer',
            'partnerSettlement',
            'approvedByUser',
        ]);

        $application = $this->linkedApplication($vendorPayment);
        $journal = JournalEntry::query()
            ->with('lines.account')
            ->whereIn('source_type', [PartnerPayment::class, VendorPayment::class])
            ->where('source_id', $vendorPayment->id)
            ->latest('id')
            ->first();

        return view('admin.vendor-payments.show', [
            'payment' => $vendorPayment,
            'application' => $application,
            'journal' => $journal,
            'inboundAccrued' => in_array((string) $vendorPayment->source_type, ['valuation_fee', 'insurance_premium'], true),
        ]);
    }

    public function approve(VendorPayment $vendorPayment, PartnerSettlementService $service): RedirectResponse
    {
        $service->approvePayment($vendorPayment, auth()->user());

        return redirect()
            ->route('admin.partner-payments.show', $vendorPayment)
            ->with('status', 'Payout approved. Record the bank transfer to mark it PAID and post cash out.');
    }

    public function pay(Request $request, VendorPayment $vendorPayment, PartnerSettlementService $service): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $service->markPaymentPaid(
            $vendorPayment,
            auth()->user(),
            $data['channel'] ?? null,
            $data['reference'] ?? null,
            $data['notes'] ?? null,
        );

        return redirect()
            ->route('admin.partner-payments.show', $vendorPayment)
            ->with('status', 'Payout recorded as PAID and posted to the ledger (partner payable ↓, cash/bank ↓).');
    }

    public function cancel(Request $request, VendorPayment $vendorPayment, PartnerSettlementService $service): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $service->cancelPayment($vendorPayment, $data['notes'] ?? null);

        return back()->with('status', 'Payment cancelled.');
    }

    private function linkedApplication(VendorPayment $payment): ?LoanApplication
    {
        if ($payment->task?->loanApplication) {
            return $payment->task->loanApplication;
        }

        if (in_array((string) $payment->source_type, ['valuation_fee', 'insurance_premium', 'vendor_task'], true)
            && (int) ($payment->source_id ?? 0) > 0) {
            return LoanApplication::query()
                ->with(['customer', 'product'])
                ->find((int) $payment->source_id);
        }

        return null;
    }

    private function applyPayoutSearch($query, string $q)
    {
        $term = '%'.$q.'%';

        return $query->where(function ($inner) use ($term, $q) {
            $inner->where('invoice_number', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('source_type', 'like', $term)
                ->orWhere('reference', 'like', $term)
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $term)->orWhere('partner_number', 'like', $term)->orWhere('phone', 'like', $term))
                ->orWhereHas('task', fn ($t) => $t->where('customer_name', 'like', $term)
                    ->orWhereHas('loanApplication', fn ($a) => $a->where('application_number', 'like', $term)));

            if (is_numeric(str_replace([',', ' '], '', $q))) {
                $inner->orWhere('amount', (float) str_replace([',', ' '], '', $q));
            }
        });
    }
}
