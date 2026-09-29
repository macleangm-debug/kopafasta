<x-site.borrower-layout :title="brand_title(__('borrower.apply.group.onboarding_title'))" active="dashboard" content-width="wide">
    @php
        $legalName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        $groupLabel = $group_name ?: __('borrower.apply.group.loan_label');
        $slides = collect($signatureCarousel ?? [])->values();
    @endphp

    <div class="max-w-3xl mx-auto space-y-6"
         x-data="{ consent: false }">
        <div>
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.apply.group.onboarding_label') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('borrower.apply.group.onboarding_title') }}</h1>
            <p class="text-sm text-gray-500 mt-2">
                {{ __('borrower.apply.group.onboarding_intro', [
                    'leader' => $invitation->leader?->full_name ?? brand_name(),
                    'product' => $invitation->product?->name ?? $groupLabel,
                ]) }}
            </p>
        </div>

        {{-- Each member: own bounded signature slot (no stacked carousel overlap) --}}
        <section class="space-y-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group.signature_carousel_title') }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.apply.group.signature_carousel_hint') }}</p>
            </div>
            @forelse ($slides as $slide)
                <article class="rounded-2xl overflow-hidden ring-1 ring-brand/12 bg-white shadow-sm">
                    <div class="px-4 sm:px-5 py-3 border-b border-brand/10 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900 truncate">{{ $slide['name'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 capitalize">
                                {{ ($slide['role'] ?? '') === 'leader' ? __('borrower.apply.group_members.leader_badge') : __('borrower.apply.group_members.member_badge') }}
                                @if (! empty($slide['is_you']))
                                    · {{ __('borrower.apply.group.signature_carousel_you') }}
                                @endif
                            </p>
                        </div>
                        <span @class([
                            'shrink-0 inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold ring-1',
                            'bg-emerald-50 text-emerald-800 ring-emerald-200' => ! empty($slide['signed']),
                            'bg-amber-50 text-amber-900 ring-amber-200' => empty($slide['signed']),
                        ])>
                            {{ $slide['status_label'] }}
                        </span>
                    </div>
                    <div class="px-4 sm:px-5 py-4">
                        <div class="rounded-xl bg-[linear-gradient(180deg,#f8faf9_0%,#ffffff_55%)] ring-1 ring-brand/10 min-h-[7.5rem] grid place-items-center px-3 py-4">
                            @if (! empty($slide['signature_data']))
                                <img src="{{ $slide['signature_data'] }}" alt="" class="max-h-24 w-auto max-w-full object-contain">
                            @else
                                <div class="text-center space-y-1 px-2">
                                    <p class="text-sm font-semibold text-amber-950">{{ __('borrower.apply.group.signature_carousel_waiting') }}</p>
                                    <p class="text-xs text-amber-900/80">{{ __('borrower.apply.group.signature_carousel_waiting_hint') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-sm text-gray-500">{{ __('borrower.apply.group.signature_carousel_waiting') }}</p>
            @endforelse
        </section>

        {{-- Confirm with saved profile signature — separate card from member slots above --}}
        <form method="POST" action="{{ route('site.group-member.onboarding.complete') }}" class="space-y-5"
              @submit.prevent="
                if (! consent) { alert(@js(__('borrower.apply.group.consent_required'))); return; }
                window.confirmForm($el, {
                    title: @js(__('borrower.apply.group.confirm_title')),
                    message: @js(__('borrower.apply.group.confirm_message')),
                    confirmLabel: @js(__('borrower.apply.group.confirm_button')),
                    confirmClass: 'bg-brand-gold hover:brightness-95 text-brand',
                });
              ">
            @csrf

            <section class="rounded-3xl overflow-hidden ring-1 ring-brand/12 bg-white shadow-sm">
                <div class="bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 sm:px-6 py-5">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.signature_legal_name') }}</p>
                    <p class="mt-2 text-xl font-bold">{{ $legalName ?: '—' }}</p>
                    <p class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold bg-white/15 px-2.5 py-1 rounded-lg">
                        <span aria-hidden="true">✓</span> {{ __('borrower.apply.signature_verified') }}
                    </p>
                </div>

                <div class="px-5 sm:px-6 py-5 space-y-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group.profile_signature_label') }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ __('borrower.apply.group.profile_signature_hint') }}</p>
                        <div class="mt-3 rounded-2xl bg-[linear-gradient(180deg,#f8faf9_0%,#ffffff_55%)] ring-1 ring-brand/10 px-3 py-5 min-h-[8rem] grid place-items-center">
                            <img src="{{ $profileSignature['signature_data'] }}" alt="" class="max-h-24 w-auto max-w-full object-contain">
                        </div>
                        <p class="mt-2 text-[11px] text-gray-500">
                            {{ __('borrower.apply.group.profile_signature_from', [
                                'date' => $profileSignature['signed_at']
                                    ? \Illuminate\Support\Carbon::parse($profileSignature['signed_at'])->timezone(config('app.timezone'))->format('d M Y')
                                    : '—',
                            ]) }}
                        </p>
                    </div>

                    <label class="flex items-start gap-3 text-sm text-gray-700 rounded-2xl bg-brand-muted/30 ring-1 ring-brand/10 p-4 cursor-pointer">
                        <input type="checkbox"
                               name="consent"
                               value="1"
                               required
                               x-model="consent"
                               class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="leading-snug">{{ __('borrower.apply.signature_consent', ['brand' => brand_name()]) }}</span>
                    </label>
                </div>
            </section>

            <button type="submit"
                    class="w-full bg-brand-gold hover:brightness-95 text-brand font-bold px-6 py-3.5 rounded-2xl text-sm shadow-sm"
                    :class="consent ? '' : 'opacity-60'">
                {{ __('borrower.apply.group.confirm_button') }}
            </button>
        </form>
    </div>
</x-site.borrower-layout>
