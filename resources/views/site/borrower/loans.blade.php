<x-site.borrower-layout :title="brand_title(__('borrower.loans_page.title'))" active="loans" content-width="wide" :portalMode="($isGuarantorPortal ?? false) ? 'guarantor' : 'borrower'">

    @php
        $isClosedApplicationRow = fn (array $row): bool => ! empty($row['is_closed'])
            || in_array((string) ($row['status'] ?? ''), ['withdrawn', 'offer_declined', 'rejected'], true);
        $loanSummary = [
            'applications' => collect($applicationRows ?? [])->reject($isClosedApplicationRow)->count(),
            'active' => ($loans ?? collect())->count(),
            'guarantor' => ($pendingGuarantorRequests ?? collect())->count()
                + ($trackingGuarantees ?? collect())->count(),
            'guaranteed' => ($guaranteedLinks ?? collect())->count(),
        ];
        $sameProductBlock = session('same_product_block');
    @endphp

    @if ($sameProductBlock)
        @php
            $blockKind = $sameProductBlock['kind'] ?? 'application';
            $isDraftBlock = $blockKind === 'draft';
        @endphp
        <div
            x-data="{ open: true }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[10050] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm" @click="open = false"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15"
                 x-transition>
                <div class="bg-gradient-to-r from-brand via-brand to-brand-light px-6 py-5 text-white">
                    <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.feedback.tones.warning') }}</p>
                    <h2 class="text-xl font-bold mt-1">{{ __('borrower.policy.same_product_title') }}</h2>
                </div>
                <div class="px-6 py-5 space-y-3">
                    <p class="text-sm text-gray-700">{{ $sameProductBlock['message'] }}</p>
                    <p class="text-xs text-gray-500">{{ __('borrower.policy.same_product_existing', ['number' => $sameProductBlock['application_number'] ?? '']) }}</p>
                    <p class="text-xs text-gray-500">{{ __('borrower.policy.same_product_hint') }}</p>
                    <div class="pt-2 space-y-2">
                        @if ($isDraftBlock)
                            <a href="{{ $sameProductBlock['continue_url'] }}"
                               class="w-full inline-flex justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-3 rounded-xl text-sm">
                                {{ __('borrower.policy.same_product_continue') }}
                            </a>
                            <form method="POST" action="{{ route('site.borrower.draft.discard', $sameProductBlock['draft_id']) }}"
                                  onsubmit="event.preventDefault(); confirmForm(this, {
                                      title: @js(__('borrower.policy.discard_draft_confirm_title')),
                                      message: @js(__('borrower.policy.discard_draft_confirm_body')),
                                      confirmLabel: @js(__('borrower.policy.discard_draft_confirm_action')),
                                      tone: 'warning',
                                      confirmClass: 'bg-red-600 hover:bg-red-700 text-white'
                                  }); return false;">
                                @csrf
                                <input type="hidden" name="reapply" value="1">
                                <button type="submit" class="w-full inline-flex justify-center bg-white ring-1 ring-red-200 text-red-700 hover:bg-red-50 font-semibold px-4 py-3 rounded-xl text-sm">
                                    {{ __('borrower.policy.withdraw_and_reapply') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('site.borrower.application', $sameProductBlock['application_id']) }}"
                               class="w-full inline-flex justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-3 rounded-xl text-sm">
                                {{ __('borrower.policy.same_product_view') }}
                            </a>
                            <form method="POST" action="{{ route('site.borrower.application.withdraw', $sameProductBlock['application_id']) }}"
                                  onsubmit="event.preventDefault(); confirmForm(this, {
                                      title: @js(__('borrower.policy.withdraw_confirm_title')),
                                      message: @js(__('borrower.policy.withdraw_confirm_body')),
                                      confirmLabel: @js(__('borrower.policy.withdraw_confirm_action')),
                                      tone: 'warning',
                                      confirmClass: 'bg-red-600 hover:bg-red-700 text-white'
                                  }); return false;">
                                @csrf
                                <button type="submit" class="w-full inline-flex justify-center bg-white ring-1 ring-red-200 text-red-700 hover:bg-red-50 font-semibold px-4 py-3 rounded-xl text-sm">
                                    {{ __('borrower.policy.withdraw_and_reapply') }}
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('site.borrower.loan-products') }}"
                           class="w-full inline-flex justify-center bg-white ring-1 ring-gray-200 text-gray-800 font-semibold px-4 py-3 rounded-xl text-sm">
                            {{ __('borrower.policy.same_product_other') }}
                        </a>
                        <button type="button" @click="open = false" class="w-full text-sm text-gray-500 hover:text-gray-700 py-2">
                            {{ __('borrower.feedback.ok') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-site.borrower-page-header
        :eyebrow="__('borrower.nav.loans')"
        :title="__('borrower.loans_page.title')"
        :subtitle="(($showGuaranteedTab ?? false) && ($activeTab ?? '') === 'guaranteed')
            ? __('borrower.loans_page.guaranteed_hint')
            : ((($showGuarantorTab ?? false) && ($activeTab ?? '') === 'guarantor')
                ? __('borrower.guarantor.tab_hint')
                : __('borrower.loans_page.subtitle'))">
        <x-slot:actions>
            <a href="{{ route('site.borrower.loan-products') }}"
               class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm shadow-sm">
                {{ __('borrower.loans_page.apply_new_cta') }}
            </a>
        </x-slot:actions>
    </x-site.borrower-page-header>




    <x-site.page-loading-shell>
        <x-slot:skeleton>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                @for ($i = 0; $i < 4; $i++)
                    <x-site.skeleton-card :lines="2" />
                @endfor
            </div>
            <x-site.skeleton-card :lines="5" />
        </x-slot:skeleton>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach ([
            ['key' => 'applications', 'label' => __('borrower.loans_page.summary_applications'), 'value' => $loanSummary['applications'], 'tone' => 'brand', 'icon' => '📋', 'tab' => 'applications'],
            ['key' => 'active', 'label' => __('borrower.loans_page.summary_active'), 'value' => $loanSummary['active'], 'tone' => 'emerald', 'icon' => '💰', 'tab' => 'active'],
            ['key' => 'guarantor', 'label' => __('borrower.loans_page.summary_guarantor'), 'value' => $loanSummary['guarantor'], 'tone' => 'amber', 'icon' => '🤝', 'tab' => ($showGuarantorTab ?? false) ? 'guarantor' : null],
            ['key' => 'guaranteed', 'label' => __('borrower.loans_page.summary_guaranteed'), 'value' => $loanSummary['guaranteed'], 'tone' => 'sky', 'icon' => '🛡', 'tab' => ($showGuaranteedTab ?? false) ? 'guaranteed' : null],
        ] as $stat)
            @php
                $toneRing = match ($stat['tone']) {
                    'emerald' => 'ring-emerald-200/80 bg-emerald-50/40',
                    'amber'   => 'ring-amber-200/80 bg-amber-50/40',
                    'sky'     => 'ring-sky-200/80 bg-sky-50/40',
                    default   => 'ring-brand/15 bg-brand-muted/30',
                };
                $statHref = $stat['tab'] ? route('site.borrower.loans', ['tab' => $stat['tab']]) : null;
                $statClass = 'glass-card h-full min-h-[5.5rem] p-4 ring-1 '.$toneRing.' flex flex-col justify-between';
            @endphp
            @if ($statHref)
                <a href="{{ $statHref }}" class="{{ $statClass }} hover:ring-brand/30 transition block">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold leading-snug line-clamp-2">{{ $stat['label'] }}</p>
                        <span class="text-lg shrink-0 leading-none" aria-hidden="true">{{ $stat['icon'] }}</span>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-gray-900 tabular-nums leading-none">{{ $stat['value'] }}</p>
                </a>
            @else
                <div class="{{ $statClass }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold leading-snug line-clamp-2">{{ $stat['label'] }}</p>
                        <span class="text-lg shrink-0 leading-none" aria-hidden="true">{{ $stat['icon'] }}</span>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-gray-900 tabular-nums leading-none">{{ $stat['value'] }}</p>
                </div>
            @endif
        @endforeach
    </div>

    <div class="mb-6">
        @include('site.borrower.loans._tabs', [
            'activeTab' => $activeTab ?? 'applications',
            'viewMode' => $viewMode ?? 'cards',
            'inline' => true,
            'showGuarantorTab' => $showGuarantorTab ?? false,
            'showGuaranteedTab' => $showGuaranteedTab ?? false,
        ])
    </div>

    @if (($activeTab ?? 'applications') === 'active')
        @include('site.borrower.loans._tab-active', ['loans' => $loans ?? collect(), 'viewMode' => $viewMode ?? 'cards'])
    @elseif (($activeTab ?? 'applications') === 'guarantor')
        @include('site.borrower.loans._tab-guarantor-requests', [
            'pendingGuarantorRequests' => $pendingGuarantorRequests ?? collect(),
            'trackingGuarantees' => $trackingGuarantees ?? collect(),
            'customer' => $customer,
            'guarantorExposure' => $guarantorExposure ?? null,
            'viewMode' => $viewMode ?? 'cards',
        ])
    @elseif (($activeTab ?? 'applications') === 'guaranteed')
        @include('site.borrower.loans._tab-guaranteed', [
            'guaranteedLinks' => $guaranteedLinks ?? collect(),
            'viewMode' => $viewMode ?? 'cards',
        ])
    @else
        @include('site.borrower.loans._tab-applications', [
            'rows' => $applicationRows ?? [],
            'viewMode' => $viewMode ?? 'cards',
        ])
    @endif

    </x-site.page-loading-shell>

</x-site.borrower-layout>
