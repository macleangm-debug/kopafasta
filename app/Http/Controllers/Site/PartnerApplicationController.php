<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerApplicationController extends Controller
{
    public function create(): View
    {
        return view('site.affiliate.apply', [
            'regions' => array_keys(config('tanzania_locations', [])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name'     => ['required', 'string', 'max:150'],
            'email'         => ['required', 'email', 'max:150'],
            'phone'         => ['required', 'string', 'max:30'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'region'        => ['nullable', 'string', 'max:100'],
            'message'       => ['nullable', 'string', 'max:2000'],
        ]);

        PartnerApplication::create([
            ...$data,
            'type'   => 'affiliate',
            'status' => 'pending',
        ]);

        return redirect()
            ->route('site.affiliate.apply')
            ->with('status', __('site.affiliate_apply.success'));
    }
}
