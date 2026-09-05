<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\PlusSubject;
use App\Models\Setting;
use App\Support\SeoDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoService
{
    /** @return array<string, mixed> */
    public function settings(): array
    {
        $seo = Setting::group('seo');
        $company = Setting::group('company');
        $siteName = filled($seo['site_name'] ?? null)
            ? (string) $seo['site_name']
            : brand_name();
        $domain = $this->normalizeDomain(
            (string) ($seo['canonical_domain'] ?? $company['website'] ?? $company['app_base_url'] ?? config('app.url'))
        );

        return [
            'site_name' => $siteName,
            'title_pattern' => (string) ($seo['title_pattern'] ?? config('seo.title_pattern', '{page} — {site}')),
            'default_description' => (string) ($seo['default_description'] ?? __('seo.default_description')),
            'default_description_sw' => (string) ($seo['default_description_sw'] ?? ''),
            'social_image' => $this->absoluteAssetUrl($seo['social_image'] ?? null, $domain),
            'canonical_domain' => $domain,
            'default_index' => $this->truthy($seo['default_index'] ?? true),
            'google_site_verification' => filled($seo['google_site_verification'] ?? null) ? (string) $seo['google_site_verification'] : null,
            'bing_site_verification' => filled($seo['bing_site_verification'] ?? null) ? (string) $seo['bing_site_verification'] : null,
            'organization_name' => (string) ($seo['organization_name'] ?? $siteName),
            'organization_legal_name' => (string) ($seo['organization_legal_name'] ?? brand_legal_name()),
            'organization_description' => (string) ($seo['organization_description'] ?? __('seo.organization_description')),
            'organization_logo' => $this->absoluteAssetUrl($seo['organization_logo'] ?? brand('logo_mark_url'), $domain),
            'same_as' => $this->lines($seo['same_as'] ?? ''),
        ];
    }

    public function environmentAllowsIndexing(): bool
    {
        if (app()->environment('staging') || app(ReleaseInfoService::class)->isStaging()) {
            return false;
        }

        return (bool) config('seo.allow_indexing', false);
    }

    public function privateDocument(Request $request, ?string $title = null, ?string $description = null): SeoDocument
    {
        return $this->forRequest($request, [
            'title' => $title,
            'description' => $description,
            'indexable' => false,
        ]);
    }

    public function forRequest(Request $request, array $overrides = []): SeoDocument
    {
        $settings = $this->settings();
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $indexable = $this->resolveIndexable($request, $overrides, $settings);
        $canonical = $this->canonicalUrl($request, $settings['canonical_domain']);
        $pageTitle = $this->resolveTitle($overrides, $settings);
        $description = $this->resolveDescription($request, $overrides, $settings, $locale);
        $image = $this->resolveImage($overrides, $settings);
        $ogType = (string) ($overrides['og_type'] ?? ((($overrides['type'] ?? 'website') === 'article') ? 'article' : 'website'));

        $jsonLd = [];
        if ($indexable) {
            $jsonLd = $this->structuredData($overrides, $settings, $canonical, $pageTitle, $description);
        }

        return new SeoDocument(
            title: $pageTitle,
            description: $description,
            canonical: $canonical,
            robots: $indexable ? 'index, follow' : 'noindex, nofollow',
            ogTitle: $pageTitle,
            ogDescription: $description,
            ogImage: $image,
            ogType: $ogType,
            locale: $locale,
            indexable: $indexable,
            jsonLd: $jsonLd,
            alternates: [],
            googleSiteVerification: $indexable ? $settings['google_site_verification'] : null,
            bingSiteVerification: $indexable ? $settings['bing_site_verification'] : null,
        );
    }

    public function forProduct(LoanProduct $product): array
    {
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $name = $product->localizedName($locale);
        $category = loan_product_type_label($product);
        $site = $this->settings()['site_name'];
        $titleOverride = $locale === 'sw'
            ? ($product->seo_title_sw ?: $product->seo_title)
            : ($product->seo_title ?: $product->seo_title_sw);
        $descOverride = $locale === 'sw'
            ? ($product->seo_description_sw ?: $product->seo_description)
            : ($product->seo_description ?: $product->seo_description_sw);

        $generatedTitle = __('seo.product_title', [
            'name' => $name,
            'category' => $category,
            'site' => $site,
        ]);

        $generatedDescription = $this->productDescription($product, $locale);

        return [
            'title' => filled($titleOverride) ? (string) $titleOverride : $generatedTitle,
            'description' => filled($descOverride) ? (string) $descOverride : $generatedDescription,
            'image' => filled($product->seo_image_path)
                ? $this->absoluteAssetUrl('storage/'.$product->seo_image_path, $this->settings()['canonical_domain'])
                : (filled($product->image_path) ? $this->absoluteAssetUrl('storage/'.$product->image_path, $this->settings()['canonical_domain']) : null),
            'indexable' => $product->seo_indexable !== false
                && in_array($product->status, ['active', 'coming_soon'], true),
            'og_type' => 'website',
        ];
    }

    public function forArticle(PlusSubject $subject): array
    {
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $title = $subject->localizedTitle($locale);
        $category = $subject->category?->localizedTitle($locale) ?: '';
        $site = $this->settings()['site_name'];
        $titleOverride = $locale === 'sw'
            ? ($subject->seo_title_sw ?: $subject->seo_title)
            : ($subject->seo_title ?: $subject->seo_title_sw);
        $descOverride = $locale === 'sw'
            ? ($subject->seo_description_sw ?: $subject->seo_description)
            : ($subject->seo_description ?: $subject->seo_description_sw);
        $excerpt = Str::limit(trim($subject->localizedIntro($locale) ?: strip_tags($subject->localizedBody($locale))), 160);

        return [
            'title' => filled($titleOverride) ? (string) $titleOverride : __('seo.learn_article_title', [
                'title' => $title,
                'category' => $category,
                'site' => $site,
            ]),
            'description' => filled($descOverride) ? (string) $descOverride : $excerpt,
            'image' => filled($subject->seo_image_path)
                ? $this->absoluteAssetUrl('storage/'.$subject->seo_image_path, $this->settings()['canonical_domain'])
                : null,
            'indexable' => $subject->status === 'published'
                && $subject->published_at
                && $subject->published_at->lte(now())
                && $subject->seo_indexable !== false,
            'type' => 'article',
            'og_type' => 'article',
            'article' => [
                'headline' => $title,
                'datePublished' => optional($subject->published_at)?->toIso8601String(),
                'dateModified' => optional($subject->updated_at)?->toIso8601String(),
            ],
        ];
    }

    public function canonicalUrl(Request $request, ?string $domain = null): string
    {
        $domain = $this->normalizeDomain($domain ?: $this->settings()['canonical_domain']);
        $path = '/'.ltrim($request->getPathInfo(), '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $domain.$path;
    }

    /** @return list<string> */
    public function indexableRouteNames(): array
    {
        return array_values(config('seo.indexable_routes', []));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $settings
     */
    private function resolveIndexable(Request $request, array $overrides, array $settings): bool
    {
        if (array_key_exists('indexable', $overrides) && $overrides['indexable'] === false) {
            return false;
        }

        if (! $this->environmentAllowsIndexing()) {
            return false;
        }

        if (! $settings['default_index']) {
            return false;
        }

        if ($this->hasNonCanonicalQuery($request)) {
            return false;
        }

        $route = (string) ($request->route()?->getName() ?? '');
        if ($this->isPrivateRoute($route, $request)) {
            return false;
        }

        if (! in_array($route, $this->indexableRouteNames(), true)) {
            return false;
        }

        if (array_key_exists('indexable', $overrides)) {
            return (bool) $overrides['indexable'];
        }

        return true;
    }

    public function isPrivateRoute(string $route, ?Request $request = null): bool
    {
        if ($route === '') {
            return true;
        }

        foreach ([
            'admin.',
            'staff.',
            'auth.two-factor.',
            'site.borrower.',
            'site.investor.',
            'site.partner.',
            'site.guarantor.',
            'site.group-member.',
            'site.short.',
            'webhooks.',
        ] as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        foreach ([
            'site.login',
            'site.register',
            'site.forgot-pin',
            'site.staff-login',
            'site.affiliate.apply',
            'site.partners.apply',
            'site.partners.apply.tracking',
            'site.member.verify',
            'site.affiliate.profile',
        ] as $exact) {
            if ($route === $exact || str_starts_with($route, $exact.'.')) {
                return true;
            }
        }

        if (in_array($route, ['site.affiliate.verify', 'site.affiliate.verify.lookup'], true)) {
            return true;
        }

        $path = $request?->getPathInfo() ?? '';
        foreach (config('seo.private_path_prefixes', []) as $prefix) {
            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/') || str_starts_with($path, $prefix))) {
                if ($prefix === '/partner/' && str_starts_with($path, '/partners')) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    public function hasNonCanonicalQuery(Request $request): bool
    {
        $allowed = array_fill_keys(config('seo.tracking_query_keys', []), true);
        foreach ($request->query() as $key => $value) {
            if ($key === '' || isset($allowed[$key])) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $settings
     */
    private function resolveTitle(array $overrides, array $settings): string
    {
        if (filled($overrides['title'] ?? null)) {
            return $this->applyTitlePattern((string) $overrides['title'], $settings);
        }

        $route = (string) (request()->route()?->getName() ?? '');

        $generated = match ($route) {
            'site.home' => __('seo.home_title'),
            'site.products' => __('seo.products_title'),
            'site.learn' => __('seo.learn_title'),
            default => brand_name(),
        };

        return $this->applyTitlePattern($generated, $settings);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $settings
     */
    private function resolveDescription(Request $request, array $overrides, array $settings, string $locale): string
    {
        if (filled($overrides['description'] ?? null)) {
            return Str::limit(trim((string) $overrides['description']), 320);
        }

        $route = (string) ($request->route()?->getName() ?? '');
        $fromLang = match ($route) {
            'site.home' => __('seo.home_description'),
            'site.products' => __('seo.products_description'),
            'site.learn' => __('seo.learn_description'),
            'site.plus' => __('site.plus.meta_desc'),
            'site.about' => __('site.about.meta_description'),
            default => null,
        };

        if (filled($fromLang)) {
            return Str::limit((string) $fromLang, 320);
        }

        if ($locale === 'sw' && filled($settings['default_description_sw'])) {
            return Str::limit($settings['default_description_sw'], 320);
        }

        return Str::limit($settings['default_description'], 320);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $settings
     */
    private function resolveImage(array $overrides, array $settings): ?string
    {
        if (filled($overrides['image'] ?? null)) {
            return $this->absoluteAssetUrl($overrides['image'], $settings['canonical_domain']);
        }

        return $settings['social_image'] ?: $this->absoluteAssetUrl(brand('logo_mark_url'), $settings['canonical_domain']);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function applyTitlePattern(string $title, array $settings): string
    {
        $site = $settings['site_name'];
        $clean = trim($title);
        if ($clean === '') {
            $clean = $site;
        }

        if (str_contains(mb_strtolower($clean), mb_strtolower($site))) {
            return $clean;
        }

        $pattern = $settings['title_pattern'] ?: '{page} — {site}';

        return strtr($pattern, [
            '{page}' => $clean,
            '{site}' => $site,
        ]);
    }

    private function productDescription(LoanProduct $product, string $locale): string
    {
        $short = $product->localizedShortDescription($locale);
        if (filled($short)) {
            return Str::limit($short, 160);
        }

        $text = (string) ($product->description ?? '');

        return Str::limit(trim($text) !== '' ? $text : __('seo.products_description'), 160);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function structuredData(array $overrides, array $settings, string $canonical, string $title, string $description): array
    {
        $graph = [
            $this->organizationSchema($settings, $canonical),
            [
                '@type' => 'WebSite',
                '@id' => $settings['canonical_domain'].'/#website',
                'url' => $settings['canonical_domain'].'/',
                'name' => $settings['site_name'],
                'inLanguage' => [app()->getLocale() === 'sw' ? 'sw' : 'en'],
                'publisher' => ['@id' => $settings['canonical_domain'].'/#organization'],
            ],
        ];

        $crumbs = $overrides['breadcrumbs'] ?? [];
        if (is_array($crumbs) && $crumbs !== []) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($crumbs)->values()->map(fn ($crumb, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'] ?? '',
                    'item' => $crumb['url'] ?? $canonical,
                ])->all(),
            ];
        }

        $faqs = $overrides['faqs'] ?? [];
        if (is_array($faqs) && $faqs !== []) {
            $accepted = collect($faqs)
                ->filter(fn ($item) => filled($item['q'] ?? null) && filled($item['a'] ?? null))
                ->values();
            if ($accepted->isNotEmpty()) {
                $graph[] = [
                    '@type' => 'FAQPage',
                    'mainEntity' => $accepted->map(fn ($item) => [
                        '@type' => 'Question',
                        'name' => $item['q'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['a'],
                        ],
                    ])->all(),
                ];
            }
        }

        if (($overrides['type'] ?? null) === 'article' && is_array($overrides['article'] ?? null)) {
            $graph[] = array_filter([
                '@type' => 'Article',
                'headline' => $overrides['article']['headline'] ?? $title,
                'description' => $description,
                'url' => $canonical,
                'datePublished' => $overrides['article']['datePublished'] ?? null,
                'dateModified' => $overrides['article']['dateModified'] ?? null,
                'image' => $this->resolveImage($overrides, $settings),
                'inLanguage' => app()->getLocale() === 'sw' ? 'sw' : 'en',
                'publisher' => ['@id' => $settings['canonical_domain'].'/#organization'],
            ]);
        }

        return [[
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ]];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function organizationSchema(array $settings, string $canonical): array
    {
        $phone = support_contact('phone');
        $email = support_contact('email');

        return array_filter([
            '@type' => 'Organization',
            '@id' => $settings['canonical_domain'].'/#organization',
            'name' => $settings['organization_name'],
            'legalName' => $settings['organization_legal_name'],
            'url' => $settings['canonical_domain'].'/',
            'description' => $settings['organization_description'],
            'logo' => $settings['organization_logo'],
            'telephone' => $phone ?: null,
            'email' => $email ?: null,
            'areaServed' => 'TZ',
            'sameAs' => $settings['same_as'] ?: null,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    private function normalizeDomain(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            $value = (string) config('app.url', 'https://www.kopafasta.co.tz');
        }
        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            $value = 'https://'.$value;
        }
        $parts = parse_url($value);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? preg_replace('#^https?://#', '', $value);

        return rtrim($scheme.'://'.$host, '/');
    }

    private function absoluteAssetUrl(mixed $path, ?string $domain = null): ?string
    {
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $domain = $this->normalizeDomain($domain ?: (string) config('app.url'));

        return $domain.'/'.ltrim($path, '/');
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];

        return array_values(array_filter(array_map('trim', $lines)));
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    public function marketplaceAssetIndexable(?MarketplaceAsset $asset): bool
    {
        if (! $asset) {
            return false;
        }

        return (bool) $asset->is_active
            && in_array((string) ($asset->availability_status ?? 'available'), ['available', 'reserved', ''], true);
    }
}
