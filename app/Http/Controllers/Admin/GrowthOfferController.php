<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlusOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrowthOfferController extends Controller
{
    public function index(): View
    {
        app(\App\Services\Plus\PlusService::class)->ensureSampleContent();

        return view('admin.growth.offers.index', [
            'offers' => PlusOffer::query()->latest('id')->limit(50)->get(),
            'dimensions' => config('marketing.audience_dimensions', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('marketing.offers.manage'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string'],
            'tier' => ['required', 'in:standard,silver,gold,platinum'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'eligible_grades' => ['nullable', 'array'],
            'plus_only' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['plus_only'] = $request->boolean('plus_only', true);
        $data['active'] = $request->boolean('active', true);
        PlusOffer::query()->create($data);

        return redirect()
            ->route('admin.growth.offers.index')
            ->with('status', 'Offer saved.');
    }
}
