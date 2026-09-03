<?php

namespace App\Support;

final class SeoDocument
{
    /**
     * @param  list<array<string, mixed>>  $jsonLd
     * @param  list<array{hreflang: string, href: string}>  $alternates
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly string $robots,
        public readonly string $ogTitle,
        public readonly string $ogDescription,
        public readonly ?string $ogImage,
        public readonly string $ogType,
        public readonly string $locale,
        public readonly bool $indexable,
        public readonly array $jsonLd,
        public readonly array $alternates,
        public readonly ?string $googleSiteVerification,
        public readonly ?string $bingSiteVerification,
    ) {}
}
