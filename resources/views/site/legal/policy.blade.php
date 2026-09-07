<x-site.layout :title="brand_title($doc['title'])">
    <x-site.legal-shell :active="$key" :heading="$doc['title']" :subheading="__('legal.policy_control.subheading', ['version' => $doc['version'], 'effective' => $doc['effective_date'] ?? '—'])">
        <div class="mb-8 rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700 grid sm:grid-cols-2 gap-2">
            <p><span class="font-semibold">{{ __('legal.policy_control.version') }}:</span> {{ $doc['version'] }}</p>
            <p><span class="font-semibold">{{ __('legal.policy_control.effective') }}:</span> {{ $doc['effective_date'] ?: '—' }}</p>
            <p><span class="font-semibold">{{ __('legal.policy_control.review') }}:</span> {{ $doc['review_date'] ?: '—' }}</p>
            <p><span class="font-semibold">{{ __('legal.policy_control.status') }}:</span> {{ ucfirst($doc['status']) }}</p>
            <p class="sm:col-span-2"><span class="font-semibold">{{ __('legal.policy_control.updated') }}:</span> {{ $doc['updated_at'] ?: '—' }}</p>
        </div>
        <article class="prose-policy max-w-none">
            {!! $html !!}
        </article>
        <div class="mt-10 pt-6 border-t border-gray-100 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('site.legal') }}" class="font-semibold text-gray-500 hover:underline">{{ __('legal.nav.hub') }}</a>
        </div>
    </x-site.legal-shell>
</x-site.layout>
