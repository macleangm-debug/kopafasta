<x-site.borrower-layout :title="brand_title('Dashboard')" active="dashboard" content-width="wide">

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Salutation --}}
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.welcome') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold mt-1">Habari, {{ $customer->first_name ?? Auth::user()->name }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('borrower.dashboard.customer_number', ['number' => $customer->customer_number ?? '—']) }}</p>
    </div>

    <x-site.borrower-dashboard-hero :hero="$dashboardHero ?? []" />

    @if ($applyDraftResume ?? null)
        <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-amber-900">{{ __('borrower.dashboard.draft_resume_title') }}</p>
                <p class="text-xs text-amber-800 mt-1">{{ __('borrower.dashboard.draft_resume_body', ['product' => $applyDraftResume['product_name']]) }}</p>
            </div>
            <a href="{{ $applyDraftResume['url'] }}"
               class="inline-flex justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-xl text-sm shrink-0">
                {{ __('borrower.dashboard.draft_resume_cta') }}
            </a>
        </div>
    @endif

    @if (! empty($groupInviteBanner['show']))
        <div class="mb-6 rounded-2xl border border-amber-300 bg-gradient-to-r from-amber-50 to-white p-5 sm:p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold mb-1">{{ __('borrower.apply.group.onboarding_label') }}</p>
                    <h2 class="text-lg font-bold text-gray-900">{{ $groupInviteBanner['title'] }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $groupInviteBanner['message'] }}</p>
                </div>
                <a href="{{ $groupInviteBanner['cta_url'] }}"
                   class="inline-flex items-center justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-xl text-sm shrink-0">
                    {{ $groupInviteBanner['cta_label'] }}
                </a>
            </div>
        </div>
    @endif

    @if (! empty($kycSectionsDue))
        <div class="mb-6 rounded-xl bg-orange-50 ring-1 ring-orange-200 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-orange-900">{{ __('borrower.dashboard.kyc_reconfirm_title') }}</p>
                <p class="text-xs text-orange-800 mt-1">{{ __('borrower.dashboard.kyc_reconfirm_body') }}</p>
            </div>
            <a href="{{ route('site.borrower.kyc-reconfirm') }}"
               class="inline-flex justify-center bg-orange-600 hover:bg-orange-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm shrink-0">
                {{ __('borrower.dashboard.kyc_reconfirm_cta') }}
            </a>
        </div>
    @endif

    @if (($openDocumentRequests ?? collect())->isNotEmpty())
        @php $firstDocRequest = $openDocumentRequests->first(); @endphp
        <div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-sky-900">{{ __('borrower.dashboard.document_requests_title') }}</p>
                <p class="text-xs text-sky-800 mt-1">{{ __('borrower.dashboard.document_requests_body', ['count' => $openDocumentRequests->count()]) }}</p>
            </div>
            @if ($firstDocRequest?->application)
                <a href="{{ route('site.borrower.application', $firstDocRequest->application) }}"
                   class="text-sm font-semibold text-sky-900 hover:underline shrink-0">{{ __('borrower.dashboard.document_requests_cta') }}</a>
            @endif
        </div>
    @endif

    {{-- Active applications --}}
    <div class="mb-8 glass-card overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100/80 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-gray-900">{{ __('borrower.active_applications') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.dashboard.active_apps_hint') }}</p>
            </div>
            <a href="{{ route('site.borrower.loans') }}" class="text-xs text-brand font-semibold hover:underline">{{ __('borrower.dashboard.view_all') }}</a>
        </div>
        @if (($activeApplications ?? collect())->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500">
                {{ __('borrower.dashboard.no_applications') }}
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($activeApplicationRows ?? [] as $row)
                    <li class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap hover:bg-brand-muted/10 transition">
                        <div class="min-w-0">
                            <a href="{{ $row['action_url'] }}" class="text-sm font-mono font-semibold text-gray-900 hover:text-brand">{{ $row['application_number'] }}</a>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $row['product_name'] }} · {{ format_money($row['requested_amount']) }}</p>
                            <div class="flex flex-wrap gap-3 mt-2 text-xs">
                                <span class="text-gray-500">{{ __('borrower.applications_list.application') }}:
                                    <span class="font-semibold text-gray-800">{{ $row['application_percent'] ?? 0 }}%</span>
                                    <span class="text-gray-500">· {{ $row['application_status'] ?? $row['status_label'] }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-brand-muted text-brand">{{ $row['status_label'] }}</span>
                            @if (! empty($row['continue_url']))
                                <a href="{{ $row['continue_url'] }}" class="text-xs font-semibold text-brand hover:underline">{{ $row['continue_label'] ?? __('borrower.applications_list.continue_application') }}</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Referral CTA --}}
    @if ($referralCode ?? null)
        <section class="mb-8 bg-brand text-white rounded-2xl p-6 sm:p-8 shadow-lg relative overflow-hidden">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.referrals.grow') }}</p>
                    <h2 class="text-xl sm:text-2xl font-bold mt-1">{{ __('borrower.dashboard.referral_title') }}</h2>
                    <p class="text-sm text-white/80 mt-2">{{ __('borrower.referrals.your_code') }}: <span class="font-mono font-bold text-white">{{ $referralCode }}</span></p>
                    <p class="text-sm text-white/80 mt-1">{{ __('borrower.dashboard.referral_wallet') }}: <span class="font-bold text-brand-gold">{{ format_money($referralWallet->balance ?? 0) }}</span></p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 shrink-0">
                    <x-site.referral-share :link="$referralLink" :code="$referralCode" :message="$referralShareMessage ?? null" />
                    <a href="{{ route('site.borrower.referrals') }}" class="inline-flex justify-center bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-2.5 rounded-xl text-sm ring-1 ring-white/20">
                        {{ __('borrower.nav.referrals') }} →
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- Loan products --}}
    <div class="mb-8">
        <div class="flex items-end justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('borrower.loan_products') }}</h2>
                <p class="text-sm text-gray-500">{{ __('borrower.dashboard.browse_products') }}</p>
            </div>
            <a href="{{ route('site.borrower.marketplace') }}" class="text-xs font-semibold text-brand hover:underline">{{ __('borrower.dashboard.marketplace_link') }}</a>
        </div>
        @if(isset($products) && $products->isNotEmpty())
            <div class="relative -mx-4 lg:mx-0" x-data="{ open: null }">
                <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory px-4 lg:px-0 pb-2">
                    @foreach($products as $p)
                        <x-site.loan-product-card :product="$p" />
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-sm text-gray-500">{{ __('borrower.dashboard_page.no_products') }}</div>
        @endif
    </div>

    {{-- Notifications --}}
    <div class="glass-card overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100/80 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-gray-900">{{ __('borrower.recent_notifications') }}</h2>
                @if (($unreadNotificationCount ?? 0) > 0)
                    <p class="text-xs text-brand font-semibold mt-0.5">{{ __('borrower.dashboard.unread_notifications', ['count' => $unreadNotificationCount]) }}</p>
                @endif
            </div>
            <a href="{{ route('site.borrower.notifications') }}" class="text-xs text-brand font-semibold hover:underline">{{ __('borrower.dashboard.view_all') }}</a>
        </div>
        @if ($notifications->isEmpty())
            <div class="p-6 text-center text-sm text-gray-500">{{ __('borrower.dashboard_page.no_messages') }}</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($notifications as $n)
                    @php
                        $lines = preg_split("/\r\n|\n|\r/", (string) ($n->message ?: '')) ?: [];
                        $title = trim($lines[0] ?? '') ?: __('borrower.notifications.fallback_title');
                        $body = trim(implode(' ', array_slice($lines, 1))) ?: ($n->message ?: $n->template);
                    @endphp
                    <li @class(['px-5 py-4 flex gap-3 transition', ! $n->read_at ? 'bg-brand-muted/20' : ''])>
                        <div class="size-9 rounded-full shrink-0 grid place-items-center {{ $n->read_at ? 'bg-gray-100 text-gray-500' : 'bg-brand-muted text-brand' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $title }}</p>
                            <p class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ $body }}</p>
                            <p class="text-[11px] text-gray-400 mt-1">{{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</x-site.borrower-layout>
