@php
    $docKey = 'legal.'.$document;
    $title = __($docKey.'.title');
    $intro = __($docKey.'.intro', [
        'brand' => brand('legal_name'),
        'email' => brand('support_email'),
    ]);
    $sections = trans($docKey.'.sections');
    if (! is_array($sections)) {
        $sections = [];
    }
@endphp
<x-site.layout :title="$title.' — Kopafasta'">
    <x-site.legal-shell :active="$document" :heading="$title" :subheading="$intro">
        <div class="space-y-8">
            @foreach ($sections as $i => $section)
                @php
                    $heading = is_array($section) ? (string) ($section[0] ?? '') : '';
                    $body = is_array($section) ? (string) ($section[1] ?? '') : '';
                    $body = strtr($body, [
                        ':brand' => brand('legal_name'),
                        ':email' => brand('support_email'),
                    ]);
                @endphp
                <section>
                    <h2 class="text-lg font-bold text-gray-900">{{ $i + 1 }}. {{ $heading }}</h2>
                    <p class="mt-2 text-sm sm:text-[15px] leading-relaxed text-gray-600">{{ $body }}</p>
                </section>
            @endforeach
        </div>
        <div class="mt-10 pt-6 border-t border-gray-100 flex flex-wrap gap-4 text-sm">
            @if ($document !== 'terms')
                <a href="{{ route('site.legal.terms') }}" class="font-semibold text-brand hover:underline">{{ __('legal.nav.terms') }} →</a>
            @endif
            @if ($document !== 'privacy')
                <a href="{{ route('site.legal.privacy') }}" class="font-semibold text-brand hover:underline">{{ __('legal.nav.privacy') }} →</a>
            @endif
            <a href="{{ route('site.legal') }}" class="font-semibold text-gray-500 hover:underline">{{ __('legal.nav.hub') }}</a>
        </div>
    </x-site.legal-shell>
</x-site.layout>
