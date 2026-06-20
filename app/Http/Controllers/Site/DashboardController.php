<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\Vendor;
use App\Models\VendorTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function borrower(): View
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $applications = $customer
            ? LoanApplication::with('product')->where('customer_id', $customer->id)->latest()->get()
            : collect();
        return view('site.borrower.dashboard', compact('customer', 'applications'));
    }

    public function vendor(): View
    {
        $vendor = Vendor::where('user_id', Auth::id())->first();
        $tasks  = $vendor ? VendorTask::where('partner_id', $vendor->id)->latest()->get() : collect();
        return view('site.vendor.dashboard', compact('vendor', 'tasks'));
    }
}
