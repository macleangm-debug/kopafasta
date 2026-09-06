<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\DisplayedRateService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(\Illuminate\Http\Request $request): View
    {
        $products = LoanProduct::with('rateTiers')->whereIn('status', ['active', 'coming_soon'])->orderBy('id')->get();
        $rateFromLabel = app(DisplayedRateService::class)->lowestBorrowerRateLabel($products);
        $featuredAssets = app(\App\Http\Controllers\Site\AssetMarketplaceController::class)->homepageFeatured(6);
        $marketplaceCategories = config('asset_marketplace.categories', []);
        $landing = app(\App\Services\LandingVariantService::class)->resolve($request);

        $regions = array_keys(config('tanzania_locations', []));

        return view('site.home', [
            'products' => $products,
            'rateFromLabel' => $rateFromLabel,
            'featuredAssets' => $featuredAssets,
            'marketplaceCategories' => $marketplaceCategories,
            'regions' => $regions,
            'landingVariant' => $landing['key'],
            'landingHeroPartial' => $landing['hero_partial'],
            'landingProductsFirst' => $landing['products_first'],
            'plusPrice' => app(\App\Services\Plus\PlusService::class)->priceForCountry(session('country', 'TZ')),
            'plusCycle' => app(\App\Services\Plus\PlusService::class)->billingCycle(),
        ]);
    }

    public function plus(): View
    {
        $plus = app(\App\Services\Plus\PlusService::class);

        return view('site.plus-public', [
            'price' => $plus->priceForCountry(session('country', 'TZ')),
            'cycle' => $plus->billingCycle(),
        ]);
    }

    public function rewards(): View
    {
        return view('site.rewards', [
            'catalog' => app(\App\Services\LoyaltyRedemptionService::class)->publicCatalog(),
            'earn' => [
                ['label' => __('site.rewards.earn_register'), 'points' => (int) config('referrals.register_points', 5)],
                ['label' => __('site.rewards.earn_apply'), 'points' => (int) config('referrals.application_points', 25)],
                ['label' => __('site.rewards.earn_profile'), 'points' => (int) (config('gamification.loyalty_points.actions.complete_profile.points', 10))],
            ],
        ]);
    }

    public function products(): View
    {
        $products = LoanProduct::with('rateTiers')->whereIn('status', ['active', 'coming_soon'])->orderBy('id')->get();
        return view('site.products.index', compact('products'));
    }

    public function product(string $code): View
    {
        $product = LoanProduct::with(['rateTiers', 'requirements', 'postApprovalFees'])
            ->where('code', $code)
            ->whereIn('status', ['active', 'coming_soon'])
            ->firstOrFail();

        $presentation = app(\App\Services\PublicProductPresentationService::class)->forProduct($product);
        $productSeo = app(\App\Services\SeoService::class)->forProduct($product);

        $otherProducts = LoanProduct::with('rateTiers')
            ->whereIn('status', ['active', 'coming_soon'])
            ->where('id', '!=', $product->id)
            ->orderBy('id')
            ->limit(4)
            ->get();

        return view('site.products.show', compact('product', 'presentation', 'otherProducts', 'productSeo'));
    }

    public function productQuote(Request $request, string $code, SmartLoanApplicationWizardService $wizard): JsonResponse
    {
        $product = LoanProduct::query()
            ->where('code', $code)
            ->whereIn('status', ['active', 'coming_soon'])
            ->firstOrFail();

        if (is_marketplace_loan_product($product->code)) {
            abort(404);
        }

        $amount = (float) $request->input('amount', $product->min_amount);
        $tenure = (int) $request->input('tenure', $product->tenure_min_months);
        $min = (float) $product->min_amount;
        $max = (float) $product->max_amount;
        $tmin = (int) $product->tenure_min_months;
        $tmax = (int) $product->tenure_max_months;
        $amount = max($min, min($max, $amount));
        $tenure = max($tmin, min($tmax, $tenure));

        $quote = $wizard->loanQuote($product, $amount, $tenure);

        return response()->json([
            'amount' => $amount,
            'tenure' => $tenure,
            'monthly_installment' => $quote['monthly_installment'],
            'weekly_installment' => $quote['weekly_installment'],
            'fees' => $quote['fees'],
            'total_repayment' => $quote['total_repayment'],
            'illustrative' => true,
        ]);
    }

    public function affiliate(): View
    {
        return view('site.affiliate.index', [
            'regions' => array_keys(config('tanzania_locations', [])),
        ]);
    }

    public function partners(): View
    {
        return view('site.partners.index');
    }

    public function country(string $code): View
    {
        $country = app(\App\Services\CountrySettingsService::class)->forCode(strtoupper($code));

        return view('site.country-coming-soon', compact('country'));
    }

    public function howItWorks(): View { return view('site.how-it-works'); }
    public function about(): View
    {
        return view('site.about');
    }

    public function aboutFounding(): View
    {
        return view('site.about.founding');
    }

    public function aboutTrust(): View
    {
        return view('site.about.trust');
    }

    public function aboutImpact(): View
    {
        return view('site.about.impact');
    }

    public function aboutRoadmap(): View
    {
        return view('site.about.roadmap');
    }

    public function faq(): View { return view('site.faq'); }
    public function invest(): View
    {
        return view('site.invest', [
            'seo' => [
                'indexable' => false,
            ],
        ]);
    }

    public function capitalPartners(): RedirectResponse
    {
        return redirect()->route('site.invest', [], 301);
    }

    public function legalIndex(): View
    {
        return view('site.legal.index');
    }

    public function terms(): View
    {
        return view('site.legal.show', ['document' => 'terms']);
    }

    public function privacy(): View
    {
        return view('site.legal.show', ['document' => 'privacy']);
    }

    public function aml(): View
    {
        return view('site.legal.show', ['document' => 'aml']);
    }

    public function complaints(): View
    {
        return view('site.legal.show', ['document' => 'complaints']);
    }

    public function cookies(): View
    {
        return view('site.legal.show', ['document' => 'cookies']);
    }
}
