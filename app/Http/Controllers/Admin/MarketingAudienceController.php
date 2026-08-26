<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingAudience;
use App\Services\Marketing\MarketingAudienceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingAudienceController extends Controller
{
    public function index(MarketingAudienceService $audiences): View
    {
        return view('admin.growth.audiences.index', [
            'audiences' => MarketingAudience::query()->latest('id')->paginate(20),
            'dimensions' => $audiences->dimensions(),
            'estimate' => null,
            'filters' => [
                'country_code' => '',
                'status' => 'active',
                'grades' => [],
                'plus' => 'any',
                'borrowing' => 'any',
                'affiliate' => 'any',
            ],
        ]);
    }

    public function estimate(Request $request, MarketingAudienceService $audiences)
    {
        $filters = $this->filters($request);

        if ($request->wantsJson() || $request->ajax()) {
            $count = $audiences->estimate($filters);

            return response()->json([
                'count' => $count,
                'compact' => \App\Support\MoneyFormat::compact($count),
                'filters' => $filters,
            ]);
        }

        return view('admin.growth.audiences.index', [
            'audiences' => MarketingAudience::query()->latest('id')->paginate(20),
            'dimensions' => $audiences->dimensions(),
            'estimate' => $audiences->estimate($filters),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, MarketingAudienceService $audiences): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $filters = $this->filters($request);
        $audience = MarketingAudience::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'filters' => $filters,
            'created_by' => $request->user()?->id,
        ]);
        $audiences->refresh($audience);

        return redirect()
            ->route('admin.growth.audiences.index')
            ->with('status', 'Audience saved. Estimated '.$audience->fresh()->estimated_count.' people.');
    }

    public function destroy(MarketingAudience $audience): RedirectResponse
    {
        $audience->delete();

        return back()->with('status', 'Audience removed.');
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'country_code' => strtoupper((string) $request->input('country_code', '')),
            'status' => (string) $request->input('status', ''),
            'grades' => array_values(array_filter((array) $request->input('grades', []))),
            'plus' => (string) $request->input('plus', 'any'),
            'borrowing' => (string) $request->input('borrowing', 'any'),
            'affiliate' => (string) $request->input('affiliate', 'any'),
        ];
    }
}
