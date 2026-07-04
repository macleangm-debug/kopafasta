@if (session('status'))
    <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
@endif

@if ($customer)
    <x-site.member-card :customer="$customer" />

    <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <section class="glass-card p-5">
            <h2 class="font-semibold text-gray-900">{{ __('borrower.membership_page.personal') }}</h2>
            <dl class="mt-3 text-sm space-y-1">
                <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('borrower.membership_page.name') }}</dt><dd class="font-medium">{{ $customer->first_name }} {{ $customer->last_name }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('borrower.membership_page.phone') }}</dt><dd class="font-medium">{{ $customer->phone ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('borrower.profile.fields.national_id') }}</dt><dd class="font-medium">{{ $customer->national_id ?? '—' }}</dd></div>
            </dl>
            <a href="{{ route('site.borrower.profile', ['section' => 'personal']) }}" class="mt-3 inline-block text-xs text-brand font-semibold hover:underline">{{ __('borrower.membership_page.edit') }}</a>
        </section>
        <section class="glass-card p-5">
            <h2 class="font-semibold text-gray-900">{{ __('borrower.membership_page.activity') }}</h2>
            <dl class="mt-3 text-sm space-y-1">
                <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('borrower.membership_page.activity_label') }}</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $customer->activity_type ?? $customer->employment_type ?? '—') }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ __('borrower.membership_page.income') }}</dt><dd class="font-medium">{{ $customer->income_range ? (income_range_label($customer->income_range) ?? '—') : ($customer->monthly_income ? 'TZS '.format_number($customer->monthly_income) : '—') }}</dd></div>
            </dl>
            <a href="{{ route('site.borrower.profile', ['section' => 'activity']) }}" class="mt-3 inline-block text-xs text-brand font-semibold hover:underline">{{ __('borrower.membership_page.edit') }}</a>
        </section>
        <section class="glass-card p-5">
            <h2 class="font-semibold text-gray-900">{{ __('borrower.membership_page.residence') }}</h2>
            <p class="mt-3 text-sm text-gray-700">{{ $customer->street ?? $customer->address ?? __('borrower.membership_page.add_address_hint') }}</p>
            @if ($customer->region)<p class="text-xs text-gray-500 mt-1">{{ $customer->ward ? $customer->ward.', ' : '' }}{{ $customer->district }}, {{ $customer->region }}</p>@endif
            <a href="{{ route('site.borrower.profile', ['section' => 'residence']) }}" class="mt-3 inline-block text-xs text-brand font-semibold hover:underline">{{ __('borrower.membership_page.edit') }}</a>
        </section>
        <section class="glass-card p-5">
            <h2 class="font-semibold text-gray-900">{{ __('borrower.membership_page.kyc') }}</h2>
            <p class="mt-3 text-sm text-gray-700">{{ __('borrower.membership_page.kyc_hint') }}</p>
            <a href="{{ route('site.borrower.profile', ['section' => 'kyc']) }}" class="mt-3 inline-block text-xs text-brand font-semibold hover:underline">{{ __('borrower.membership_page.view_kyc') }}</a>
        </section>
    </div>

    @if ($referralCode ?? null)
        <section class="mt-8 bg-gradient-to-br from-brand-muted to-amber-50 rounded-2xl ring-1 ring-brand/10 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-gray-900">{{ __('borrower.membership_page.referral_program') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.membership_page.referral_summary', ['code' => $referralCode, 'balance' => format_money($referralWallet->balance ?? 0)]) }}</p>
            </div>
            <a href="{{ route('site.borrower.referrals') }}" class="shrink-0 inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-semibold px-5 py-2.5 rounded-xl text-sm">{{ __('borrower.membership_page.open_referrals') }}</a>
        </section>
    @endif
@else
    <div class="glass-card p-6 text-sm text-gray-700">
        {{ __('borrower.membership_page.no_profile') }}
    </div>
@endif

<div class="mt-8">
    <h2 class="text-lg font-semibold text-gray-900 mb-3">{{ __('borrower.membership_page.history_title') }}</h2>
    <div class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50/80 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">{{ __('borrower.membership_page.col_date') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('borrower.membership_page.col_event') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('borrower.membership_page.col_issued') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('borrower.membership_page.col_expires') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('borrower.membership_page.col_renewals') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('borrower.membership_page.col_payment_ref') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($history ?? [] as $h)
                        <tr>
                            <td class="px-4 py-2">{{ $h->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-2 capitalize">{{ str_replace('_', ' ', $h->event) }}</td>
                            <td class="px-4 py-2">{{ optional($h->issued_at)->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-2">{{ optional($h->expires_at)->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $h->renewal_count_after ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $h->payment_reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500 text-sm">{{ __('borrower.membership_page.history_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if (session('confetti'))
    <script>
        (function () {
            const n = 120;
            const colors = ['#f59e0b','#10b981','#3b82f6','#ef4444','#a855f7'];
            for (let i = 0; i < n; i++) {
                const d = document.createElement('div');
                d.style.cssText = `position:fixed;top:-10px;left:${Math.random()*100}vw;width:8px;height:14px;background:${colors[i%colors.length]};opacity:.9;z-index:9999;transform:rotate(${Math.random()*360}deg);transition:transform 2.6s ease-out, top 2.6s ease-out, opacity 2.6s;`;
                document.body.appendChild(d);
                requestAnimationFrame(() => {
                    d.style.top = (80 + Math.random()*20) + 'vh';
                    d.style.transform = `translateX(${(Math.random()-.5)*200}px) rotate(${Math.random()*720}deg)`;
                    d.style.opacity = '0';
                });
                setTimeout(() => d.remove(), 2800);
            }
        })();
    </script>
@endif
