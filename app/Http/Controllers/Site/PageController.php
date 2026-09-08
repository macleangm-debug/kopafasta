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
        $products = public_catalogue_products();
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
        $actions = (array) config('gamification.loyalty_points.actions', []);
        $earn = collect($actions)
            ->filter(fn ($row, $key) => (int) ($row['points'] ?? 0) > 0 && ! in_array($key, ['borrow', 'increase_loan', 'repeat_borrow'], true))
            ->map(fn ($row, $key) => [
                'key' => $key,
                'label' => match ($key) {
                    'repay_on_time' => __('site.rewards.earn_repay'),
                    'complete_profile' => __('site.rewards.earn_profile'),
                    'complete_goal' => __('site.rewards.earn_goal'),
                    default => (string) ($row['label'] ?? ucwords(str_replace('_', ' ', (string) $key))),
                },
                'points' => (int) $row['points'],
            ])
            ->values()
            ->all();

        $earn = array_values(array_filter(array_merge([
            ['label' => __('site.rewards.earn_register'), 'points' => (int) config('referrals.register_points', 5)],
            ['label' => __('site.rewards.earn_apply'), 'points' => (int) config('referrals.application_points', 25)],
            ['label' => __('site.rewards.earn_repay'), 'points' => (int) data_get($actions, 'repay_on_time.points', 0)],
            ['label' => __('site.rewards.earn_profile'), 'points' => (int) data_get($actions, 'complete_profile.points', 10)],
            ['label' => __('site.rewards.earn_goal'), 'points' => (int) data_get($actions, 'complete_goal.points', 0)],
        ], $earn), fn ($row) => (int) ($row['points'] ?? 0) > 0));

        // De-duplicate by label while preserving order.
        $seen = [];
        $earn = array_values(array_filter($earn, function ($row) use (&$seen) {
            $key = mb_strtolower($row['label']);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));

        return view('site.rewards', [
            'catalog' => app(\App\Services\LoyaltyRedemptionService::class)->publicCatalog(),
            'earn' => $earn,
        ]);
    }

    public function products(): View
    {
        $products = public_catalogue_products();
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

        $otherProducts = public_catalogue_products()
            ->where('id', '!=', $product->id)
            ->take(4)
            ->values();

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
