<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\DisplayedRateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $products = LoanProduct::with('rateTiers')->whereIn('status', ['active', 'coming_soon'])->orderBy('id')->get();
        $rateFromLabel = app(DisplayedRateService::class)->lowestBorrowerRateLabel($products);
        $featuredAssets = app(\App\Http\Controllers\Site\AssetMarketplaceController::class)->homepageFeatured(6);
        $marketplaceCategories = config('asset_marketplace.categories', []);

        $regions = array_keys(config('tanzania_locations', []));

        return view('site.home', compact('products', 'rateFromLabel', 'featuredAssets', 'marketplaceCategories'));
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

        return view('site.products.show', compact('product', 'presentation'));
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
    public function about(): View { return view('site.about'); }
    public function faq(): View { return view('site.faq'); }
    public function invest(): View { return view('site.invest'); }
    public function capitalPartners(): View { return view('site.capital-partners'); }
}
