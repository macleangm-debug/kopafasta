<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingDemoSession;
use App\Services\Marketing\DemoContext;
use App\Services\Marketing\MarketingDemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class MarketingDemoController extends Controller
{
    public function index(MarketingDemoService $demos): View
    {
        $demos->expireOverdue();

        return view('admin.growth.demos.index', [
            'demos' => MarketingDemoSession::query()->latest('id')->paginate(20),
        ]);
    }

    public function create(MarketingDemoService $demos): View
    {
        $demos->ensureSystemPersonas();
        $unrestricted = (bool) request()->user()?->hasPermission('marketing.demos.unrestricted');

        return view('admin.growth.demos.create', [
            'personas' => \App\Models\MarketingPersona::query()->orderBy('name')->get(),
            'scenarios' => config('marketing.scenarios', []),
            'durations' => config('marketing.demo_durations', []),
            'unrestricted' => $unrestricted,
        ]);
    }

    public function store(Request $request, MarketingDemoService $demos): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('marketing.demos.create'), 403);
        $data = $request->validate([
            'who' => ['required', 'in:borrower,plus,affiliate'],
            'persona_key' => ['required', 'string', 'max:80'],
            'scenario_key' => ['required', 'string', 'max:80'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'amount' => ['nullable'],
            'grade' => ['nullable', 'in:bronze,silver,gold,platinum'],
            'trust' => ['nullable', 'integer', 'min:0', 'max:100'],
            'duration' => ['required', 'in:5,15,30,60,today,custom'],
            'custom_expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        if (isset($data['amount'])) {
            $data['amount'] = \App\Support\MoneyFormat::toNumber($data['amount']);
        }
        $session = $demos->create(
            $data,
            $request->user(),
            (bool) $request->user()?->hasPermission('marketing.demos.unrestricted'),
        );

        return redirect()
            ->route('admin.growth.demos.show', $session)
            ->with('status', 'Demo ready. It is isolated from real customers, ledgers and payments.');
    }

    public function show(MarketingDemoSession $demo, MarketingDemoService $demos): View
    {
        $demos->expireOverdue();
        $demo->refresh();

        return view('admin.growth.demos.show', [
            'demo' => $demo,
            'playUrl' => URL::temporarySignedRoute('admin.growth.demos.play', now()->addHours(12), ['demo' => $demo]),
        ]);
    }

    public function play(Request $request, MarketingDemoSession $demo, DemoContext $context, MarketingDemoService $demos): View
    {
        abort_unless($request->hasValidSignature() || $request->user('admin'), 403);
        $demos->expireOverdue();
        $demo->refresh();
        abort_unless($demo->isLive(), 410, 'This demo has ended.');
        $context->activate($demo);
        $demos->record($demo, 'opened', [], $request->user('admin'));

        return view('admin.growth.demos.play', [
            'demo' => $demo,
            'presentation' => (bool) $request->boolean('presentation'),
        ]);
    }

    public function customize(Request $request, MarketingDemoSession $demo, MarketingDemoService $demos): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('marketing.demos.create'), 403);
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'amount' => ['nullable'],
            'grade' => ['nullable', 'in:bronze,silver,gold,platinum'],
            'trust' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        if (isset($data['amount'])) {
            $data['amount'] = \App\Support\MoneyFormat::toNumber($data['amount']);
        }
        $demos->customize(
            $demo,
            $data,
            $request->user(),
            (bool) $request->user()?->hasPermission('marketing.demos.unrestricted'),
        );

        return back()->with('status', 'Presentation values updated. Still isolated — no customer or ledger row was written.');
    }

    public function end(Request $request, MarketingDemoSession $demo, MarketingDemoService $demos): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('marketing.demos.end')
            || $request->user()?->hasPermission('marketing.demos.create'), 403);
        $demos->end($demo, 'ended', $request->user());

        return redirect()
            ->route('admin.growth.demos.index')
            ->with('status', 'Demo ended and archived. Nothing was written to real customer tables.');
    }
}
