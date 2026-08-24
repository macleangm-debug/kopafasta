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

    public function borrowerIndex(CardVerificationService $cards): View
    {
        return view('site.borrower.verify', [
            'types' => $cards->types(),
            'result' => null,
            'selectedType' => old('type', 'member'),
            'number' => old('number', ''),
            'showForm' => true,
        ]);
    }

    public function lookup(Request $request, CardVerificationService $cards): RedirectResponse
    {
        return $this->resolveLookup($request, $cards, 'public');
    }

    public function borrowerLookup(Request $request, CardVerificationService $cards): RedirectResponse
    {
        return $this->resolveLookup($request, $cards, 'borrower');
    }

    public function partnerIndex(CardVerificationService $cards): View
    {
        return view('site.vendor.verify', $this->verifyPageData($cards));
    }

    public function partnerLookup(Request $request, CardVerificationService $cards): RedirectResponse
    {
        return $this->resolveLookup($request, $cards, 'partner');
    }

    public function partnerShowMember(string $memberNo, CardVerificationService $cards): View
    {
        return view('site.vendor.verify', $this->verifyPageData($cards, $cards->lookup('member', $memberNo), 'member'));
    }

    public function partnerShowPartner(string $partnerNo, CardVerificationService $cards): View
    {
        $result = $cards->resolvePartnerToken($partnerNo);

        return view('site.vendor.verify', $this->verifyPageData($cards, $result, $result['type'] ?? 'supplier'));
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

    public function borrowerShowMember(string $memberNo, CardVerificationService $cards): View
    {
        $result = $cards->lookup('member', $memberNo);

        return view('site.borrower.verify', [
            'types' => $cards->types(),
            'result' => $result,
            'selectedType' => 'member',
            'number' => '',
            'showForm' => false,
        ]);
    }

    public function borrowerShowPartner(string $partnerNo, CardVerificationService $cards): View
    {
        $result = $cards->resolvePartnerToken($partnerNo);

        return view('site.borrower.verify', [
            'types' => $cards->types(),
            'result' => $result,
            'selectedType' => $result['type'] ?? 'supplier',
            'number' => '',
            'showForm' => false,
        ]);
    }

    private function verifyPageData(CardVerificationService $cards, ?array $result = null, ?string $selectedType = null): array
    {
        return [
            'types' => $cards->types(),
            'result' => $result,
            'selectedType' => $selectedType ?? old('type', 'member'),
            'number' => old('number', ''),
            'showForm' => $result === null,
        ];
    }

    private function resolveLookup(Request $request, CardVerificationService $cards, string $shell): RedirectResponse
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

        $memberRoute = match ($shell) {
            'borrower' => 'site.borrower.verify.member',
            'partner' => 'site.partner.verify.member',
            default => 'site.short.member',
        };
        $partnerRoute = match ($shell) {
            'borrower' => 'site.borrower.verify.partner',
            'partner' => 'site.partner.verify.partner',
            default => 'site.short.partner',
        };

        if ($data['type'] === 'member') {
            return redirect()->route($memberRoute, ['memberNo' => $id]);
        }

        return redirect()->route($partnerRoute, ['partnerNo' => $id]);
    }
}
