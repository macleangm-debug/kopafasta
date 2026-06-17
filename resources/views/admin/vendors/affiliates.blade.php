<x-admin.layout title="Affiliate Partners" heading="Affiliate Partners" subheading="Marketing partners with tracked referral links, discounts, and commission">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">
            Affiliate partners receive unique codes and links. Track clicks, registrations, and application conversions from each partner profile.
        </p>
        <a href="{{ route('admin.partners.create', ['category' => 'affiliate']) }}"
           class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-lg text-sm">
            + New affiliate partner
        </a>
    </div>

    @livewire('admin.vendors-table', ['category' => 'affiliate', 'lockCategory' => true, 'affiliateMode' => true])
</x-admin.layout>
