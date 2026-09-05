<x-admin.layout title="Website SEO" heading="Website SEO" subheading="Global defaults for public discovery. Private account pages stay noindex.">
    @include('admin.settings._tabs', ['active' => 'seo'])
    @php
        $sameAs = $values['same_as'] ?? '';
        if (is_array($sameAs)) {
            $sameAs = implode("\n", $sameAs);
        }
    @endphp
    <x-admin.settings-editor
        action="{{ route('admin.settings.seo.save') }}"
        submit-label="Save SEO defaults"
        enctype="multipart/form-data"
        :tabs="[
            'defaults' => 'Defaults',
            'social' => 'Social & verification',
            'organization' => 'Organization',
        ]"
    >
        <x-admin.settings-panel id="defaults">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <p class="text-sm text-gray-600">
                    These values apply when a public page does not have its own title or description.
                    Loan products and Plus articles can override them on the content record.
                </p>
                @unless ($indexingAllowed)
                    <p class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3 text-sm text-amber-900">
                        This environment is not production. Every page is <strong>noindex, nofollow</strong> regardless of the default below, so a staging domain cannot be indexed as a duplicate site.
                    </p>
                @endunless
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="site_name" label="Site / brand name" :value="$values['site_name'] ?? brand_name()" />
                    <x-admin.input name="title_pattern" label="Default title pattern" :value="$values['title_pattern'] ?? '{page} — {site}'" help="Use {page} and {site}. Ignored when a page title already includes the brand name." />
                    <x-admin.input name="canonical_domain" label="Public canonical domain" :value="$values['canonical_domain'] ?? ''" placeholder="https://www.kopafasta.com" help="Host only, with https. Canonicals and the sitemap use this instead of a hard-coded domain." />
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default index behavior</label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="hidden" name="default_index" value="0">
                            <input type="checkbox" name="default_index" value="1" @checked(($values['default_index'] ?? true) !== false && ($values['default_index'] ?? true) !== '0') class="rounded border-gray-300 text-brand focus:ring-brand/30">
                            Allow indexing of public pages in production
                        </label>
                    </div>
                </div>
                <x-admin.textarea name="default_description" label="Default meta description (English)" :value="$values['default_description'] ?? ''" rows="3" maxlength="320" />
                <x-admin.textarea name="default_description_sw" label="Default meta description (Kiswahili)" :value="$values['default_description_sw'] ?? ''" rows="3" maxlength="320" />
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="social">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <x-admin.input name="social_image" label="Default social image URL or path" :value="$values['social_image'] ?? ''" placeholder="/images/brand/kopafasta-mark.png" />
                <label class="block text-sm font-medium text-gray-700">Or upload a social image
                    <input type="file" name="social_image_file" accept="image/*" class="mt-1 block w-full text-sm text-gray-600">
                </label>
                <div class="grid md:grid-cols-2 gap-4">
                    <x-admin.input name="google_site_verification" label="Google Search Console verification" :value="$values['google_site_verification'] ?? ''" />
                    <x-admin.input name="bing_site_verification" label="Bing Webmaster verification" :value="$values['bing_site_verification'] ?? ''" />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="organization">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div class="grid md:grid-cols-2 gap-4">
                    <x-admin.input name="organization_name" label="Organization name" :value="$values['organization_name'] ?? brand_name()" />
                    <x-admin.input name="organization_legal_name" label="Legal name" :value="$values['organization_legal_name'] ?? brand_legal_name()" />
                </div>
                <x-admin.textarea name="organization_description" label="Organization description" :value="$values['organization_description'] ?? ''" rows="3" />
                <x-admin.input name="organization_logo" label="Organization logo path" :value="$values['organization_logo'] ?? ''" />
                <x-admin.textarea name="same_as" label="Same-as profile URLs (one per line)" :value="$sameAs" rows="3" help="Optional social or company profiles. Do not invent reviews or ratings." />
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
