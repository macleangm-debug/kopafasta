<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingDemoSession;
use App\Models\Partner;
use App\Models\PlusOffer;
use App\Models\Promotion;
use Illuminate\View\View;

class GrowthController extends Controller
{
    public function index(): View
    {
        $activeCampaigns = Promotion::query()->where('status', 'active')->get();
        $endingSoon = Promotion::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->count();
        $reviewCampaigns = Promotion::query()
            ->where('status', 'draft')
            ->get()
            ->filter(fn (Promotion $promo) => ($promo->metadata['send_mode'] ?? '') === 'schedule')
            ->count();
        $activeOffers = PlusOffer::query()->where('active', true)->get();
        $expiringOffers = PlusOffer::query()
            ->where('active', true)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->count();
        $liveDemos = MarketingDemoSession::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();
        $expiringDemos = $liveDemos->filter(function (MarketingDemoSession $demo) {
            return $demo->expires_at && $demo->expires_at->lte(now()->addHours(2));
        })->count();

        $reached = 0;
        $opened = 0;
        $converted = 0;
        foreach ($activeCampaigns as $campaign) {
            $results = $campaign->metadata['results'] ?? [];
            $reached += (int) ($results['reach'] ?? $campaign->metadata['estimated_reach'] ?? 0);
            $opened += (int) ($results['opened'] ?? 0) + (int) ($results['clicked'] ?? 0);
            $converted += (int) ($results['converted'] ?? 0);
        }

        $affiliates = 0;
        if (class_exists(Partner::class) && \Illuminate\Support\Facades\Schema::hasTable('partners')) {
            $affiliates = Partner::query()->where('category', 'affiliate')->count();
        }

        $engagement = $reached > 0
            ? round(($opened / $reached) * 100).'%'
            : '—';

        return view('admin.growth.index', [
            'stats' => [
                'campaigns' => $activeCampaigns->count(),
                'reached' => $reached,
                'engagement' => $engagement,
                'conversions' => $converted,
                'offers' => $activeOffers->count(),
                'affiliates' => $affiliates,
                'demos' => $liveDemos->count(),
            ],
            'attention' => [
                ['label' => $endingSoon.' campaign'.($endingSoon === 1 ? '' : 's').' ending this week', 'show' => $endingSoon > 0, 'url' => route('admin.promotions.index')],
                ['label' => $reviewCampaigns.' scheduled campaign'.($reviewCampaigns === 1 ? '' : 's').' waiting to go live', 'show' => $reviewCampaigns > 0, 'url' => route('admin.promotions.index')],
                ['label' => $expiringOffers.' offer'.($expiringOffers === 1 ? '' : 's').' expiring this week', 'show' => $expiringOffers > 0, 'url' => route('admin.growth.offers.index')],
                ['label' => $expiringDemos.' demo'.($expiringDemos === 1 ? '' : 's').' expiring soon', 'show' => $expiringDemos > 0, 'url' => route('admin.growth.demos.index')],
            ],
            'running' => $activeCampaigns->sortByDesc('id')->take(6)->values(),
            'runningOffers' => $activeOffers->sortByDesc('id')->take(4)->values(),
            'liveDemos' => $liveDemos->count(),
        ]);
    }

    public function affiliates(): View
    {
        $affiliateCount = 0;
        if (class_exists(Partner::class) && \Illuminate\Support\Facades\Schema::hasTable('partners')) {
            $affiliateCount = Partner::query()->where('category', 'affiliate')->count();
        }

        return view('admin.growth.affiliates', [
            'affiliateCount' => $affiliateCount,
        ]);
    }

    public function performance(): View
    {
        $active = Promotion::query()->where('status', 'active')->get();
        $reached = 0;
        $converted = 0;
        foreach ($active as $campaign) {
            $results = $campaign->metadata['results'] ?? [];
            $reached += (int) ($results['reach'] ?? 0);
            $converted += (int) ($results['converted'] ?? 0);
        }

        return view('admin.growth.performance', [
            'stats' => [
                'campaigns' => $active->count(),
                'reached' => $reached,
                'converted' => $converted,
                'offers' => PlusOffer::query()->where('active', true)->count(),
            ],
        ]);
    }
}
