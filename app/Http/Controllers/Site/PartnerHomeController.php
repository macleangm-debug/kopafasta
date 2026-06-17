<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
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

        return app(VendorController::class)->dashboard($request);
    }
}
