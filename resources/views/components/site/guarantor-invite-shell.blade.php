@props([
    'title',
    'eyebrow' => null,
    'heading' => null,
    'lede' => null,
    'asideSteps' => [],
])

@php
    $eyebrow = $eyebrow ?? __('borrower.guarantor_invite.heading');
    $heading = $heading ?? __('borrower.guarantor_invite.heading');
    $lede = $lede ?? __('borrower.guarantor_invite.request_explanation');
    $asideSteps = $asideSteps !== [] ? $asideSteps : [
        [__('borrower.guarantor_invite.shell_step_review'), __('borrower.guarantor_invite.shell_step_review_hint')],
        [__('borrower.guarantor_invite.shell_step_decide'), __('borrower.guarantor_invite.shell_step_decide_hint')],
        [__('borrower.guarantor_invite.shell_step_continue'), __('borrower.guarantor_invite.shell_step_continue_hint')],
    ];
@endphp

{{-- auth=true: full-viewport split like Login/Register — no site footer gap --}}
<x-site.layout :title="$title" :auth="true">
    <section class="h-full min-h-0 grid lg:grid-cols-2 premium-gradient overflow-hidden">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-10 xl:p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>

            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ $eyebrow }}</p>
                <h2 class="mt-2 text-3xl xl:text-4xl font-bold tracking-tight leading-tight">{{ $heading }}</h2>
                <p class="mt-4 text-white/70 max-w-md text-sm leading-relaxed">{{ $lede }}</p>

                <ol class="mt-10 space-y-4">
                    @foreach ($asideSteps as $i => [$label, $hint])
                        <li class="flex items-start gap-3">
                            <span class="size-8 grid place-items-center rounded-full text-xs font-bold flex-shrink-0 bg-white/10 text-white/70">{{ $i + 1 }}</span>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $label }}</p>
                                <p class="text-xs text-white/50">{{ $hint }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <p class="relative text-xs text-white/50">
                {{ __('borrower.guarantor_invite.shell_footer') }}
            </p>
        </aside>

        <div class="h-full min-h-0 overflow-y-auto overscroll-y-contain flex items-start lg:items-center justify-center px-4 py-6 sm:px-10 sm:py-10">
            <div class="w-full max-w-md">
                <div class="mb-4 flex items-center justify-between lg:hidden">
                    <a href="{{ route('site.home') }}"><x-site.brand-mark size="sm" /></a>
                    <span class="text-[10px] font-semibold uppercase tracking-widest text-amber-700">{{ $eyebrow }}</span>
                </div>

                @if (session('status'))
                    <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                @endif

                <div class="rounded-2xl bg-white/95 shadow-xl shadow-brand/5 ring-1 ring-gray-200/80 p-5 sm:p-7">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
