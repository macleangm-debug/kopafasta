<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartnerApplicationController extends Controller
{
    public function create(): \Illuminate\View\View
    {
        return view('site.affiliate.apply', [
            'regions' => array_keys(config('tanzania_locations', [])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applicant_category' => ['required', 'in:individual,company,institution'],
            'full_name'          => ['required', 'string', 'max:150'],
            'email'              => ['required', 'email', 'max:150'],
            'phone'              => ['required', 'string', 'max:30'],
            'business_name'      => ['nullable', 'string', 'max:150'],
            'region'             => ['nullable', 'string', 'max:100'],
            'message'            => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($data['applicant_category'], ['company', 'institution'], true) && blank($data['business_name'] ?? null)) {
            return back()->withErrors(['business_name' => __('site.affiliate_apply.business_required')])->withInput();
        }

        PartnerApplication::create([
            ...$data,
            'type'   => 'affiliate',
            'status' => 'pending',
        ]);

        return redirect()
            ->route('site.affiliate')
            ->with('status', __('site.affiliate_apply.success'));
    }
}
