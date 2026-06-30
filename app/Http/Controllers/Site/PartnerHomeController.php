<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PartnerHomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        if (! Auth::check()) {
            return redirect()->route('site.partner.start');
        }

        $vendor = Vendor::where('user_id', Auth::id())->first();
        if ($vendor?->isAffiliate()) {
            return redirect()->route('site.affiliate.dashboard');
        }

        if ($vendor?->isSupplier()) {
            return redirect()->route('site.supplier.dashboard');
        }

        return app(VendorController::class)->dashboard($request);
    }
}
