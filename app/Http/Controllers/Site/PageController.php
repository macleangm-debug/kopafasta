<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\DisplayedRateService;
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

        $otherProducts = LoanProduct::with('rateTiers')
            ->whereIn('status', ['active', 'coming_soon'])
            ->where('id', '!=', $product->id)
            ->orderBy('id')
            ->limit(4)
            ->get();

        return view('site.products.show', compact('product', 'presentation', 'otherProducts'));
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
    public function invest(): View { return view('site.invest'); }
    public function capitalPartners(): View { return view('site.capital-partners'); }

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
