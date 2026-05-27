<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\LoanApplication;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $customerId = (int) $request->query('customer_id');
        $customer = Customer::with(['applications', 'loans', 'kyc'])->findOrFail($customerId);

        return response()->json([
            'customer' => $customer,
            'active_loans' => $customer->loans()->whereIn('status', ['approved', 'active', 'in_arrears'])->count(),
            'pending_applications' => $customer->applications()->whereNotIn('status', ['rejected', 'disbursed'])->count(),
        ]);
    }

    public function submitKyc(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'payload' => ['nullable', 'array'],
        ]);

        $kyc = CustomerKyc::updateOrCreate(
            ['customer_id' => $data['customer_id']],
            ['status' => 'pending', 'payload' => $data['payload'] ?? null]
        );

        return response()->json($kyc);
    }

    public function trackApplication(LoanApplication $loanApplication)
    {
        return response()->json(
            $loanApplication->load(['customer', 'product', 'stageHistory'])
        );
    }
}
