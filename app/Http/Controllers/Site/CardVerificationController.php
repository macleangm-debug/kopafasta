<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\CardVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardVerificationController extends Controller
{
    public function index(CardVerificationService $cards): View
    {
        return view('site.public.card-verify', [
            'types' => $cards->types(),
            'result' => null,
            'selectedType' => old('type', 'member'),
            'number' => old('number', ''),
            'showForm' => true,
        ]);
    }

    public function lookup(Request $request, CardVerificationService $cards): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'number' => ['required', 'string', 'max:40'],
        ]);

        $types = $cards->types();
        if (! isset($types[$data['type']])) {
            return back()->withErrors(['type' => __('site.card_verify.invalid_type')])->withInput();
        }

        $id = $cards->composeId($data['type'], $data['number']);
        if (! $id) {
            return back()->withErrors(['number' => __('site.card_verify.invalid_number')])->withInput();
        }

        if ($data['type'] === 'member') {
            return redirect()->route('site.short.member', ['memberNo' => $id]);
        }

        return redirect()->route('site.short.partner', ['partnerNo' => $id]);
    }

    public function showPartner(string $partnerNo, CardVerificationService $cards): View
    {
        $result = $cards->resolvePartnerToken($partnerNo);

        return view('site.public.card-verify', [
            'types' => $cards->types(),
            'result' => $result,
            'selectedType' => $result['type'] ?? 'supplier',
            'number' => '',
            'showForm' => false,
        ]);
    }
}
