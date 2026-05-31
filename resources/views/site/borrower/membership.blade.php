<x-site.borrower-layout title="Membership — Kopafasta" active="membership">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="mb-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">My membership</p>
        <h1 class="text-2xl sm:text-3xl font-bold">KopaFasta Member Card</h1>
        <p class="text-sm text-gray-500 mt-1">Your one-year membership keeps you eligible for loans, services and renewals.</p>
    </div>

    @if ($customer)
        <x-site.member-card :customer="$customer" />

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <section class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                <h2 class="font-semibold text-gray-900">Who are you?</h2>
                <p class="text-xs text-gray-500 mt-1">Personal details</p>
                <dl class="mt-3 text-sm space-y-1">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Name</dt><dd class="font-medium">{{ $customer->first_name }} {{ $customer->last_name }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ $customer->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">NIDA</dt><dd class="font-medium">{{ $customer->national_id ?? '—' }}</dd></div>
                </dl>
                <a href="{{ route('site.borrower.profile') }}" class="mt-3 inline-block text-xs text-amber-600 font-semibold hover:underline">Edit profile →</a>
            </section>
            <section class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                <h2 class="font-semibold text-gray-900">What do you do?</h2>
                <p class="text-xs text-gray-500 mt-1">Employment & business</p>
                <dl class="mt-3 text-sm space-y-1">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Type</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $customer->employment_type ?? '—') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Business</dt><dd class="font-medium">{{ $customer->business_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Income</dt><dd class="font-medium">{{ $customer->monthly_income ? 'TZS '.number_format($customer->monthly_income) : '—' }}</dd></div>
                </dl>
            </section>
            <section class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                <h2 class="font-semibold text-gray-900">Where do you live?</h2>
                <p class="text-xs text-gray-500 mt-1">Residence information</p>
                <p class="mt-3 text-sm text-gray-700">{{ $customer->address ?? 'Add your address in profile.' }}</p>
            </section>
            <section class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                <h2 class="font-semibold text-gray-900">KYC information</h2>
                <p class="text-xs text-gray-500 mt-1">Verification documents</p>
                <p class="mt-3 text-sm text-gray-700">Upload KYC during your loan application process.</p>
                <a href="{{ route('site.borrower.applications') }}" class="mt-3 inline-block text-xs text-amber-600 font-semibold hover:underline">View applications →</a>
            </section>
        </div>
    @else
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-200 p-6 text-sm text-gray-700">
            No customer profile linked to this account yet.
        </div>
    @endif

    <div class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Membership history</h2>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Event</th>
                        <th class="px-4 py-2 text-left">Issued</th>
                        <th class="px-4 py-2 text-left">Expires</th>
                        <th class="px-4 py-2 text-left">Renewals</th>
                        <th class="px-4 py-2 text-left">Payment ref</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($history as $h)
                        <tr>
                            <td class="px-4 py-2">{{ $h->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-2 capitalize">{{ str_replace('_', ' ', $h->event) }}</td>
                            <td class="px-4 py-2">{{ optional($h->issued_at)->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-2">{{ optional($h->expires_at)->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $h->renewal_count_after ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $h->payment_reference ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500 text-sm">No history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (session('confetti'))
        <script>
            // Lightweight confetti — no external dep needed.
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
</x-site.borrower-layout>
