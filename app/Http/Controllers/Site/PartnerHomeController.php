<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PinService;
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

        $user = Auth::user();
        if ($user->role !== 'vendor') {
            return redirect()->route('site.login.partner')
                ->with('warning', __('site.auth.use_partner_login'));
        }

        if (! app(PinService::class)->hasPin($user)) {
            return redirect()->route('site.partner.setup-pin')
                ->with('status', 'Create your 4-digit PIN to secure your partner account.');
        }

        $vendor = Vendor::where('user_id', $user->id)->first();
        if ($vendor?->isAffiliate()) {
            return redirect()->route('site.affiliate.dashboard');
        }

        if ($vendor?->isSupplier()) {
            return redirect()->route('site.supplier.dashboard');
        }

        if ($vendor?->category === 'capital' || \App\Models\Lender::query()->where('user_id', $user->id)->exists()) {
            return redirect()->route('site.investor.dashboard');
        }

        return app(VendorController::class)->dashboard();
    }
}
