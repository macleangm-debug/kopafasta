<x-site.borrower-layout title="Dashboard — Kopafasta" active="dashboard">

    {{-- Greeting --}}
    <div class="flex items-start justify-between gap-3 mb-6 flex-wrap">
        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">Welcome back</p>
            <h1 class="text-2xl sm:text-3xl font-bold">Habari, {{ $customer->first_name ?? Auth::user()->name }} 👋</h1>
            <p class="text-sm text-gray-500 mt-1">Customer #{{ $customer->customer_number ?? '—' }}</p>
        </div>
        <a href="{{ route('site.borrower.apply') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full inline-flex items-center gap-2 text-sm">
            + New application
        </a>
    </div>

    {{-- Eligibility hero --}}
    @if ($eligibility['amount'] > 0)
        <div class="mb-6 rounded-2xl bg-gradient-to-r from-gray-900 to-gray-800 text-white p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-amber-300 font-semibold">You qualify for up to</p>
                <p class="mt-1 text-3xl sm:text-4xl font-extrabold">TZS {{ number_format($eligibility['amount']) }}</p>
                <p class="mt-2 text-xs text-gray-300 max-w-md">Based on your income {{ $eligibility['has_data'] ? '' : '(estimate — complete your profile for a better offer)' }}. Final approval after credit check.</p>
            </div>
            <a href="{{ route('site.borrower.apply') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-3 rounded-full text-sm whitespace-nowrap inline-flex items-center gap-2">Apply now →</a>
        </div>
    @endif

    {{-- Available loan products --}}
    <div class="mb-8">
        <h2 class="text-lg font-semibold mb-4">Available loan products</h2>
        @if(isset($products) && $products->isNotEmpty())
            <div class="space-y-4" x-data="{ open: null }">
                @foreach($products as $p)
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <button type="button" @click="open = open === {{ $p->id }} ? null : {{ $p->id }}"
                                class="w-full text-left p-5 sm:p-6 flex items-start justify-between gap-4 hover:bg-gray-50 transition">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="text-[10px] font-mono font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">{{ $p->code }}</span>
                                    <span class="text-xs text-gray-500">{{ number_format((float)$p->interest_rate * 100, 1) }}% monthly · {{ $p->tenure_min_months }}–{{ $p->tenure_max_months }} mo</span>
                                </div>
                                <h3 class="text-lg font-bold text-gray-900">{{ $p->name }}</h3>
                                <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $p->description ?: 'Configured loan product from admin settings.' }}</p>
                                <p class="text-sm font-semibold text-gray-900 mt-2">TZS {{ number_format($p->min_amount) }} – {{ number_format($p->max_amount) }}</p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400 shrink-0 transition-transform" :class="open === {{ $p->id }} && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div x-show="open === {{ $p->id }}" x-transition x-cloak class="border-t border-gray-100 px-5 sm:px-6 pb-5">
                            <dl class="grid sm:grid-cols-2 gap-3 text-sm pt-4">
                                <div><dt class="text-gray-500">Eligibility</dt><dd class="font-medium">{{ Str::limit($p->description ?: 'Active membership and completed application required', 80) }}</dd></div>
                                <div><dt class="text-gray-500">Repayment period</dt><dd class="font-medium">{{ $p->tenure_min_months }}–{{ $p->tenure_max_months }} months</dd></div>
                                <div><dt class="text-gray-500">Rate range</dt><dd class="font-medium">{{ number_format((float)$p->interest_rate * 100, 2) }}% per month</dd></div>
                                <div><dt class="text-gray-500">Loan limits</dt><dd class="font-medium">TZS {{ number_format($p->min_amount) }} – {{ number_format($p->max_amount) }}</dd></div>
                            </dl>
                            <a href="{{ route('site.borrower.apply', ['product' => $p->id]) }}"
                               class="mt-4 inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                                Apply for {{ $p->name }} →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-sm text-gray-500">No loan products available at the moment.</div>
        @endif
    </div>

    {{-- KYC reminder --}}
    @php
        $kycStatus = $kyc->status ?? 'pending';
        $kycComplete = ($kycRequired > 0 && $kycUploaded >= $kycRequired);
        $showKycCard = $kycStatus !== 'approved';
        $customerKind = match ($customer->type ?? 'individual') {
            'business' => 'company',
            'group'    => 'group',
            default    => 'individual',
        };
    @endphp
    @if ($showKycCard)
        @php
            $kycBg = match ($kycStatus) {
                'rejected'  => ['bg' => 'bg-red-50 ring-red-200',     'text' => 'text-red-700',     'btn' => 'bg-red-600 hover:bg-red-700 text-white',     'bar' => 'bg-red-500'],
                'in_review' => ['bg' => 'bg-blue-50 ring-blue-200',   'text' => 'text-blue-700',   'btn' => 'bg-blue-600 hover:bg-blue-700 text-white',   'bar' => 'bg-blue-500'],
                default     => ['bg' => 'bg-amber-50 ring-amber-200', 'text' => 'text-amber-800', 'btn' => 'bg-amber-500 hover:bg-amber-400 text-gray-900', 'bar' => 'bg-amber-500'],
            };
        @endphp
        <div class="mb-6 rounded-2xl {{ $kycBg['bg'] }} ring-1 p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                <div class="min-w-0">
                    <p class="font-semibold {{ $kycBg['text'] }}">
                        @if ($kycStatus === 'rejected')      KYC was rejected — please re-upload
                        @elseif ($kycStatus === 'in_review') KYC under review
                        @elseif ($kycComplete)               KYC submitted — awaiting review
                        @else                                Complete your {{ $customerKind }} KYC
                        @endif
                    </p>
                    <p class="text-sm {{ $kycBg['text'] }} opacity-80 mt-0.5">
                        {{ $kycUploaded }} of {{ $kycRequired }} required document(s) uploaded for your <strong>{{ $customerKind }}</strong> account.
                    </p>
                </div>
                <a href="{{ route('site.borrower.kyc') }}" class="{{ $kycBg['btn'] }} font-semibold px-5 py-2.5 rounded-full text-sm whitespace-nowrap inline-flex items-center gap-2">
                    {{ $kycComplete ? 'View status' : 'Upload KYC →' }}
                </a>
            </div>

            {{-- Progress meter --}}
            <div class="mb-3">
                <div class="flex items-center justify-between text-xs {{ $kycBg['text'] }} mb-1">
                    <span class="font-semibold">Progress</span>
                    <span class="font-mono font-bold">{{ $kycProgress ?? 0 }}%</span>
                </div>
                <div class="h-2 bg-white/70 rounded-full overflow-hidden">
                    <div class="h-full {{ $kycBg['bar'] }} transition-all" style="width: {{ $kycProgress ?? 0 }}%"></div>
                </div>
            </div>

            {{-- Missing list --}}
            @if (! empty($kycMissing) && $kycMissing->isNotEmpty())
                <div class="text-xs {{ $kycBg['text'] }}">
                    <p class="font-semibold mb-1">Still needed:</p>
                    <ul class="space-y-0.5 opacity-90">
                        @foreach ($kycMissing as $m)
                            <li class="flex items-start gap-1.5">
                                <span class="mt-1 inline-block w-1.5 h-1.5 rounded-full {{ $kycBg['bar'] }}"></span>
                                <span>{{ $m->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    {{-- Summary cards --}}
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 font-medium mb-1">Active loan</p>
            <p class="text-xl font-bold text-gray-900 truncate">{{ $activeLoan ? 'TZS '.number_format($activeLoan->principal_amount) : '—' }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $activeLoan->loan_number ?? 'None active' }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 font-medium mb-1">Outstanding balance</p>
            <p class="text-xl font-bold text-gray-900">{{ $activeLoan ? 'TZS '.number_format($activeLoan->outstanding_balance) : 'TZS 0' }}</p>
            <p class="text-xs text-emerald-600 mt-1">Pay anytime · no penalty</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 font-medium mb-1">Next payment</p>
            <p class="text-xl font-bold text-gray-900">{{ $nextDue ? \Carbon\Carbon::parse($nextDue->due_date)->format('d M Y') : '—' }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $nextDue ? 'TZS '.number_format($nextDue->total_due) : 'No upcoming payments' }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 font-medium mb-1">Loan status</p>
            @php
                $st = $activeLoan->status ?? ($latestApplication->status ?? 'none');
                $color = match (true) {
                    in_array($st, ['active','disbursed','approved']) => 'bg-emerald-100 text-emerald-700',
                    in_array($st, ['arrears','rejected'])            => 'bg-red-100 text-red-700',
                    $st === 'none'                                    => 'bg-gray-100 text-gray-700',
                    default                                           => 'bg-amber-100 text-amber-700',
                };
            @endphp
            <span class="inline-flex text-xs font-semibold rounded-full px-2.5 py-1 {{ $color }}">{{ ucfirst(str_replace('_',' ', $st)) }}</span>
            <p class="text-xs text-gray-500 mt-2">{{ $applicationsCount }} application(s) total</p>
        </div>
    </div>

    {{-- Two-column lower section --}}
    <div class="grid lg:grid-cols-3 gap-6">

        {{-- Latest application --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold">Latest application</h2>
                <a href="{{ route('site.borrower.applications') }}" class="text-xs text-amber-600 hover:underline">View all →</a>
            </div>
            @if ($latestApplication)
                <div class="p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-mono font-semibold">{{ $latestApplication->application_number }}</p>
                            <p class="text-xs text-gray-500">{{ $latestApplication->product->name ?? '—' }} · TZS {{ number_format($latestApplication->requested_amount) }} · {{ $latestApplication->requested_tenure_months }} mo</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-700">{{ ucfirst(str_replace('_',' ', $latestApplication->status)) }}</span>
                    </div>
                    @php
                        $stages = ['submitted','screening','credit_appraisal','pre_approval','approval','disbursement'];
                        $idx = array_search($latestApplication->status, $stages);
                        $pct = $idx === false ? 10 : (($idx + 1) / count($stages)) * 100;
                    @endphp
                    <div>
                        <div class="flex justify-between text-[10px] text-gray-500 mb-1">
                            <span>Submitted</span><span>Disbursed</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-500" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-10 text-center text-sm text-gray-500">
                    No applications yet.
                    <a href="{{ route('site.borrower.apply') }}" class="text-amber-600 font-medium hover:underline ml-1">Start your first →</a>
                </div>
            @endif
        </div>

        {{-- Notifications preview --}}
        <div class="bg-white rounded-2xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold">Notifications</h2>
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
    </div>

</x-site.borrower-layout>

