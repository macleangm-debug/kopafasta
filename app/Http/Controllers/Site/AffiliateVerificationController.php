<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\AffiliateService;
use App\Services\AffiliateSettingsService;
use App\Services\GuarantorInvitationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $code = trim((string) $request->query('code', ''));
        $phone = trim((string) $request->query('phone', ''));

        if ($code !== '') {
            return $this->show($code);
        }

        if ($phone !== '') {
            return $this->lookup($request);
        }

        return view('site.public.affiliate-verify', [
            'affiliate'  => null,
            'code'       => null,
            'phone'      => null,
            'verified'   => false,
            'notice'     => null,
            'verify_url' => null,
            'lookup'     => true,
        ]);
    }

    public function lookup(Request $request): View
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'code'  => ['nullable', 'string', 'max:40'],
        ]);

        if (filled($data['code'] ?? null)) {
            return $this->show((string) $data['code']);
        }

        $phone = app(GuarantorInvitationService::class)->normalizePhone($data['phone'] ?? '');
        $affiliate = null;

        if ($phone !== '') {
            $affiliate = Vendor::query()
                ->where('category', 'affiliate')
                ->get()
                ->first(function (Vendor $vendor) use ($phone): bool {
                    $normalized = app(GuarantorInvitationService::class)->normalizePhone($vendor->phone);

                    return $normalized !== '' && $normalized === $phone;
                });
        }

        return $this->renderResult($affiliate, null, $phone);
    }

    public function show(string $code): View
    {
        $affiliate = Vendor::query()
            ->where('category', 'affiliate')
            ->where(function ($q) use ($code): void {
                $q->where('affiliate_code', strtoupper(trim($code)))
                    ->orWhere('partner_number', strtoupper(trim($code)));
            })
            ->first();

        return $this->renderResult($affiliate, strtoupper(trim($code)), null);
    }

    private function renderResult(?Vendor $affiliate, ?string $code, ?string $phone): View
    {
        $requireKyc = app(AffiliateSettingsService::class)->requireKycForVerification();
        $membershipOk = $affiliate
            ? app(\App\Services\AffiliateMembershipService::class)->isSharingAllowed($affiliate)
            : false;

        $verified = $affiliate
            && $affiliate->status === 'active'
            && $membershipOk
            && (! $requireKyc || in_array($affiliate->affiliate_kyc_status, ['verified', 'approved'], true));

        $notice = $affiliate
            ? app(AffiliateService::class)->renderMessage($affiliate, 'verification_notice')
            : null;

        return view('site.public.affiliate-verify', [
            'affiliate'  => $affiliate,
            'code'       => $code,
            'phone'      => $phone,
            'verified'   => $verified,
            'notice'     => $notice,
            'verify_url' => $affiliate
                ? route('site.affiliate.verify', $affiliate->affiliate_code ?? $code ?? 'unknown')
                : null,
            'lookup'     => true,
        ]);
    }
}
