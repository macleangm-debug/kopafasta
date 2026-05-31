<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $products = LoanProduct::whereIn('status', ['active', 'coming_soon'])->orderBy('id')->get();
        return view('site.home', compact('products'));
    }

    public function products(): View
    {
        $products = LoanProduct::whereIn('status', ['active', 'coming_soon'])->orderBy('id')->get();
        return view('site.products.index', compact('products'));
    }

    public function product(string $code): View
    {
        $product = LoanProduct::where('code', $code)
            ->whereIn('status', ['active', 'coming_soon'])
            ->firstOrFail();
        return view('site.products.show', compact('product'));
    }

    public function howItWorks(): View { return view('site.how-it-works'); }
    public function about(): View { return view('site.about'); }
    public function faq(): View { return view('site.faq'); }
    public function invest(): View { return view('site.invest'); }
    public function capitalPartners(): View { return view('site.capital-partners'); }
}
