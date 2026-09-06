<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\PlusSubject;
use App\Models\PlusSubjectCategory;
use Illuminate\Support\Carbon;

class SitemapService
{
    public function __construct(private readonly SeoService $seo) {}

    public function xml(): string
    {
        $urls = $this->urls();
        $body = collect($urls)->map(function (array $row) {
            $lastmod = isset($row['lastmod'])
                ? '<lastmod>'.Carbon::parse($row['lastmod'])->toAtomString().'</lastmod>'
                : '';

            return '<url><loc>'.e($row['loc']).'</loc>'.$lastmod.'</url>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$body
            .'</urlset>';
    }

    /** @return list<array{loc: string, lastmod?: mixed}> */
    public function urls(): array
    {
        $domain = $this->seo->settings()['canonical_domain'];
        $now = now();
        $urls = [];

        foreach ($this->staticPaths() as $path) {
            $urls[] = ['loc' => $domain.$path, 'lastmod' => $now];
        }

        LoanProduct::query()
            ->whereIn('status', ['active', 'coming_soon'])
            ->where(function ($q) {
                $q->whereNull('seo_indexable')->orWhere('seo_indexable', true);
            })
            ->orderBy('id')
            ->get(['code', 'updated_at'])
            ->each(function (LoanProduct $product) use (&$urls, $domain) {
                $urls[] = [
                    'loc' => $domain.'/loans/product/'.$product->code,
                    'lastmod' => $product->updated_at,
                ];
            });

        PlusSubjectCategory::query()
            ->where('status', 'published')
            ->orderBy('sort')
            ->get(['slug', 'updated_at'])
            ->each(function (PlusSubjectCategory $category) use (&$urls, $domain) {
                $urls[] = [
                    'loc' => $domain.'/learn/'.$category->slug,
                    'lastmod' => $category->updated_at,
                ];
            });

        PlusSubject::query()
            ->published()
            ->where(function ($q) {
                $q->whereNull('seo_indexable')->orWhere('seo_indexable', true);
            })
            ->with('category:id,slug')
            ->orderBy('id')
            ->get(['id', 'slug', 'plus_subject_category_id', 'updated_at', 'published_at'])
            ->each(function (PlusSubject $subject) use (&$urls, $domain) {
                $category = $subject->category?->slug;
                if (! $category) {
                    return;
                }
                $urls[] = [
                    'loc' => $domain.'/learn/'.$category.'/'.$subject->slug,
                    'lastmod' => $subject->updated_at ?: $subject->published_at,
                ];
            });

        MarketplaceAsset::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'updated_at'])
            ->each(function (MarketplaceAsset $asset) use (&$urls, $domain) {
                $urls[] = [
                    'loc' => $domain.'/marketplace/'.$asset->id,
                    'lastmod' => $asset->updated_at,
                ];
            });

        return $urls;
    }

    /** @return list<string> */
    private function staticPaths(): array
    {
        return [
            '/',
            '/loans',
            '/how-it-works',
            '/plus',
            '/rewards',
            '/about',
            '/about/founding-story',
            '/about/trust',
            '/about/impact',
            '/about/roadmap',
            '/faq',
            '/legal',
            '/legal/terms',
            '/legal/privacy',
            '/legal/aml-kyc',
            '/legal/complaints',
            '/legal/cookies',
            '/support',
            '/affiliate-program',
            '/service-partners',
            '/marketplace',
            '/learn',
            '/verify',
            '/verify/affiliate',
        ];
    }
}
