@props([
    'header' => [],
    'sections' => [],
    'meta' => [],
    'intro' => null,
    'introHtml' => null,
    'footer' => null,
])

<article class="glass-card overflow-hidden">
    <div class="bg-gradient-to-br from-brand via-brand-light to-brand px-6 py-8 text-white">
        <div class="flex items-start justify-between gap-4">
            <x-site.brand-mark size="md" variant="light" />
            @if (! empty($header['affiliate_type']))
                <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] ring-1 ring-white/25">
                    {{ $header['affiliate_type'] }}
                </span>
            @endif
        </div>
        <h1 class="mt-6 text-2xl sm:text-3xl font-extrabold tracking-tight">{{ $header['title'] ?? '' }}</h1>
        <dl class="mt-6 grid sm:grid-cols-2 gap-3 text-sm">
            @foreach ([
                __('site.affiliate_portal.doc_affiliate') => $header['affiliate_name'] ?? null,
                __('site.affiliate_portal.doc_id') => $header['affiliate_id'] ?? null,
                __('site.affiliate_portal.doc_version') => isset($header['agreement_version']) ? 'v'.$header['agreement_version'] : null,
                __('site.affiliate_portal.doc_effective') => $header['effective_date'] ?? null,
                __('site.affiliate_portal.doc_contract_term') => $header['contract_term'] ?? null,
                __('site.affiliate_portal.doc_period') => filled($header['start_date'] ?? null) ? ($header['start_date'].' – '.($header['end_date'] ?? '—')) : null,
                __('site.affiliate_portal.doc_accepted') => $header['accepted_at'] ?? null,
            ] as $label => $value)
                @if (filled($value))
                    <div class="rounded-xl bg-white/10 ring-1 ring-white/20 px-3.5 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-white/70">{{ $label }}</dt>
                        <dd class="font-semibold mt-1">{{ $value }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </div>

    <div class="px-6 py-8 space-y-8">
        @if (! empty($meta))
            <dl class="grid sm:grid-cols-2 gap-3">
                @foreach ($meta as $row)
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $row['label'] ?? '' }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $row['value'] ?? '' }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if (filled($introHtml ?? null))
            <div class="prose prose-sm max-w-none text-gray-700">{!! $introHtml !!}</div>
        @elseif (filled($intro))
            <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-line">{{ $intro }}</div>
        @endif

        @foreach ($sections as $index => $section)
            @php
                $title = preg_replace('/^\d+\.\s+/', '', (string) ($section['title'] ?? '')) ?? ($section['title'] ?? '');
            @endphp
            <section class="space-y-3">
                <h2 class="text-lg font-bold text-gray-900">
                    <span class="text-brand mr-2">{{ $index + 1 }}.</span>{{ $title }}
                </h2>
                @if (! empty($section['body_html']))
                    <div class="prose prose-sm max-w-none text-gray-700">{!! $section['body_html'] !!}</div>
                @else
                    <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-line">{{ $section['body'] ?? '' }}</div>
                @endif
            </section>
        @endforeach

        @if ($footer)
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 text-sm text-gray-600">
                {{ $footer }}
            </div>
        @endif

        {{ $slot }}
    </div>
</article>
