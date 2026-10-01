{{-- Shared Help Centre landing body (borrower + partner). --}}
@php
    $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
    $helpCategories = $helpCategories ?? [];
    $helpRecommended = $helpRecommended ?? [];
    $helpResults = $helpResults ?? [];
    $helpQuery = $helpQuery ?? '';
    $homeUrl = $homeUrl ?? route('site.borrower.support');
    $chatUrl = $chatUrl ?? route('site.borrower.support', ['chat' => 1]);
    $phones = $phones ?? support_phones();
    $primaryPhone = $primaryPhone ?? ($phones[0] ?? support_contact('phone'));
    $supportHours = class_exists(\App\Models\Setting::class)
        ? (\App\Models\Setting::get('company.support_hours') ?: \App\Models\Setting::get('company.hours'))
        : null;
@endphp

@if ($helpQuery !== '')
    <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-3">
        <p class="text-sm font-semibold text-gray-900">{{ $isSw ? 'Matokeo' : 'Results' }} ({{ count($helpResults) }})</p>
        @forelse ($helpResults as $row)
            <a href="{{ $row['url'] }}" class="block rounded-xl bg-slate-50 hover:bg-brand-muted/30 px-4 py-3 transition">
                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ $row['group'] }}</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ $row['title'] }}</p>
                <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $row['body'] }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-500">{{ $isSw ? 'Hakuna matokeo. Jaribu maneno mengine au Ongea na timu.' : 'No matches. Try different words or Talk to Support.' }}</p>
        @endforelse
    </section>
@else
    {{-- Category carousel --}}
    <section>
        <div class="flex items-end justify-between gap-3 mb-3">
            <h2 class="font-semibold text-lg text-gray-900">{{ $isSw ? 'Chagua mada' : 'Browse topics' }}</h2>
        </div>
        <div class="overflow-x-auto pb-2 -mx-1 px-1 snap-x snap-mandatory scrollbar-none">
            <div class="flex gap-3 w-max lg:w-full lg:grid lg:grid-cols-4 xl:grid-cols-5 lg:gap-4">
                @foreach ($helpCategories as $cat)
                    <a href="{{ route('site.help.category', $cat['key']) }}"
                       class="snap-start shrink-0 w-[9.5rem] sm:w-[10.5rem] lg:w-auto rounded-2xl bg-white ring-1 ring-brand/10 hover:ring-brand/30 shadow-sm px-4 py-4 transition text-left">
                        <span class="text-2xl" aria-hidden="true">{{ $cat['icon'] }}</span>
                        <p class="mt-3 text-sm font-bold text-gray-900 leading-snug">{{ $cat['label'] }}</p>
                        <p class="mt-1 text-[11px] font-semibold text-brand/80">
                            {{ $isSw ? ('Mada '.$cat['topic_count']) : ($cat['topic_count'].' topics') }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @if ($helpRecommended !== [])
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $isSw ? 'Inayopendekezwa kwako' : 'Suggested for you' }}</p>
            <ul class="mt-3 space-y-2">
                @foreach ($helpRecommended as $rec)
                    <li>
                        <a href="{{ $rec['url'] }}" class="text-sm font-semibold text-gray-900 hover:text-brand">
                            {{ $rec['title'] }} →
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endif

{{-- Three support cards --}}
<section class="grid grid-cols-3 gap-2 sm:gap-4">
    <a href="{{ $chatUrl }}"
       class="rounded-2xl bg-white ring-1 ring-brand/15 hover:ring-brand/30 px-2.5 sm:px-5 py-4 text-center sm:text-left transition shadow-sm">
        <p class="text-lg sm:text-xl" aria-hidden="true">💬</p>
        <p class="mt-2 text-[11px] sm:text-sm font-bold text-gray-900 leading-snug">{{ $isSw ? 'Ongea na timu' : 'Talk to Support' }}</p>
        <p class="mt-1 text-[10px] sm:text-xs text-gray-500 leading-snug hidden sm:block">{{ $isSw ? 'Pata msaada kutoka kwa timu yetu.' : 'Get help from our team.' }}</p>
    </a>
    <button type="button"
            @click="$dispatch('open-feedback')"
            class="rounded-2xl bg-white ring-1 ring-brand/15 hover:ring-brand/30 px-2.5 sm:px-5 py-4 text-center sm:text-left transition shadow-sm">
        <p class="text-lg sm:text-xl" aria-hidden="true">✉️</p>
        <p class="mt-2 text-[11px] sm:text-sm font-bold text-gray-900 leading-snug">{{ $isSw ? 'Tuma maoni' : 'Send feedback' }}</p>
        <p class="mt-1 text-[10px] sm:text-xs text-gray-500 leading-snug hidden sm:block">{{ $isSw ? 'Tuma swali, pendekezo au malalamiko.' : 'Send a question, suggestion or complaint.' }}</p>
    </button>
    @if ($primaryPhone)
        <a href="tel:{{ preg_replace('/\s+/', '', $primaryPhone) }}"
           class="rounded-2xl bg-white ring-1 ring-brand/15 hover:ring-brand/30 px-2.5 sm:px-5 py-4 text-center sm:text-left transition shadow-sm">
            <p class="text-lg sm:text-xl" aria-hidden="true">☎️</p>
            <p class="mt-2 text-[11px] sm:text-sm font-bold text-gray-900 leading-snug">{{ $isSw ? 'Piga simu' : 'Call us' }}</p>
            <p class="mt-1 text-[10px] sm:text-xs text-brand font-semibold leading-snug tabular-nums">{{ $primaryPhone }}</p>
            @if (filled($supportHours))
                <p class="mt-0.5 text-[10px] text-gray-500 hidden sm:block">{{ $supportHours }}</p>
            @endif
        </a>
    @else
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-2.5 sm:px-5 py-4 text-center sm:text-left opacity-70">
            <p class="text-lg sm:text-xl" aria-hidden="true">☎️</p>
            <p class="mt-2 text-[11px] sm:text-sm font-bold text-gray-900">{{ $isSw ? 'Piga simu' : 'Call us' }}</p>
            <p class="mt-1 text-[10px] text-gray-500">{{ $isSw ? 'Nambari itakuja hivi karibuni' : 'Number coming soon' }}</p>
        </div>
    @endif
</section>
