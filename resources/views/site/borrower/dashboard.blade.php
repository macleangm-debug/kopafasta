<x-site.borrower-layout :title="brand_title('Dashboard')" active="dashboard">

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

    <x-site.onboarding-hero-banner :banner="$onboardingBanner" />

    @if ($applyDraftResume ?? null)
        <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-amber-900">{{ __('borrower.dashboard.draft_resume_title') }}</p>
                <p class="text-xs text-amber-800 mt-1">{{ __('borrower.dashboard.draft_resume_body', ['product' => $applyDraftResume['product_name']]) }}</p>
            </div>
            <a href="{{ $applyDraftResume['url'] }}"
               class="inline-flex justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm shrink-0">
                {{ __('borrower.dashboard.draft_resume_cta') }}
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

    {{-- Welcome strip --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.welcome') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold mt-1">Habari, {{ $customer->first_name ?? Auth::user()->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('borrower.dashboard.customer_number', ['number' => $customer->customer_number ?? '—']) }}</p>
        </div>
        <a href="{{ ($applyRequirements['can_apply'] ?? false) ? route('site.borrower.apply') : route('site.borrower.dashboard') }}"
           @class([
               'font-semibold px-5 py-2.5 rounded-full inline-flex items-center gap-2 text-sm shrink-0 self-start',
               ($applyRequirements['can_apply'] ?? false)
                   ? 'bg-amber-500 hover:bg-amber-400 text-gray-900'
                   : 'bg-gray-100 text-gray-400 cursor-not-allowed pointer-events-none',
           ])>
            + {{ __('borrower.new_application') }}
        </a>
    </div>

    {{-- Referral CTA --}}
    @if ($referralCode ?? null)
        <section class="mb-8 bg-gradient-to-br from-indigo-700 via-indigo-800 to-slate-900 text-white rounded-2xl p-6 sm:p-8 shadow-lg">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <p class="text-xs uppercase tracking-widest text-indigo-200 font-semibold">{{ __('borrower.referrals.grow') }}</p>
                    <h2 class="text-xl sm:text-2xl font-bold mt-1">{{ __('borrower.dashboard.referral_title') }}</h2>
                    <p class="text-sm text-indigo-100 mt-2">{{ __('borrower.referrals.your_code') }}: <span class="font-mono font-bold text-white">{{ $referralCode }}</span></p>
                    <p class="text-sm text-indigo-100 mt-1">{{ __('borrower.dashboard.referral_wallet') }}: <span class="font-bold text-white">TZS {{ number_format($referralWallet->balance ?? 0) }}</span></p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 shrink-0">
                    <x-site.referral-share :link="$referralLink" :code="$referralCode" />
                    <a href="{{ route('site.borrower.referrals') }}" class="inline-flex justify-center bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-2.5 rounded-full text-sm ring-1 ring-white/20">
                        {{ __('borrower.nav.referrals') }} →
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- Active applications --}}
    <div class="mb-8 bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="font-semibold">{{ __('borrower.active_applications') }}</h2>
            <a href="{{ route('site.borrower.applications') }}" class="text-xs text-amber-600 hover:underline">{{ __('borrower.dashboard.view_all') }}</a>
        </div>
        @if (($activeApplications ?? collect())->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500">
                {{ __('borrower.dashboard.no_applications') }}
                @if ($applyRequirements['can_apply'] ?? false)
                    <a href="{{ route('site.borrower.apply') }}" class="text-amber-600 font-medium hover:underline ml-1">{{ __('borrower.dashboard.start_application') }}</a>
                @endif
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($activeApplications as $app)
                    <li class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <a href="{{ route('site.borrower.application', $app) }}" class="text-sm font-mono font-semibold text-gray-900 hover:text-amber-700">{{ $app->application_number }}</a>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $app->product->name ?? '—' }} · TZS {{ number_format($app->requested_amount) }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-700">{{ ucfirst(str_replace('_',' ', $app->status)) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Loan products --}}
    <div class="mb-8">
        <div class="flex items-end justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('borrower.loan_products') }}</h2>
                <p class="text-sm text-gray-500">{{ __('borrower.dashboard.browse_products') }}</p>
            </div>
            <a href="{{ route('site.borrower.marketplace') }}" class="text-xs font-semibold text-amber-700 hover:underline">{{ __('borrower.dashboard.marketplace_link') }}</a>
        </div>
        @if(isset($products) && $products->isNotEmpty())
            <div class="relative -mx-4 lg:mx-0" x-data="{ open: null }">
                <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory px-4 lg:px-0 pb-2">
                    @foreach($products as $p)
                        @if (is_marketplace_loan_product($p->code))
                            <div class="snap-start shrink-0 w-[min(85vw,320px)] bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
                                <div class="bg-gradient-to-br from-sky-500 to-sky-700 text-white px-5 py-4">
                                    <p class="text-lg font-bold">{{ $p->name }}</p>
                                    <p class="text-xs opacity-90 mt-1">{{ __('borrower.marketplace.subtitle') }}</p>
                                </div>
                                <div class="p-5 flex-1 flex flex-col">
                                    <p class="text-sm text-gray-600">{{ __('borrower.dashboard.marketplace_link') }}</p>
                                    <a href="{{ route('site.borrower.marketplace') }}" class="mt-auto inline-flex justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                                        {{ __('borrower.nav.marketplace') }} →
                                    </a>
                                </div>
                            </div>
                        @else
                            <x-site.loan-product-card :product="$p" />
                        @endif
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-sm text-gray-500">No loan products available at the moment.</div>
        @endif
    </div>

    {{-- Notifications --}}
    <div class="bg-white rounded-2xl border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="font-semibold">{{ __('borrower.recent_notifications') }}</h2>
            <a href="{{ route('site.borrower.notifications') }}" class="text-xs text-amber-600 hover:underline">All →</a>
        </div>
        @if ($notifications->isEmpty())
            <div class="p-6 text-center text-sm text-gray-500">No messages yet.</div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($notifications as $n)
                    <li class="px-5 py-3">
                        <p class="text-sm text-gray-800 truncate">{{ $n->message ?: $n->template }}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</x-site.borrower-layout>
