<x-admin.layout title="Integrations" heading="Integrations" subheading="Payment, SMS, email, and compliance partners — configure, check health, review usage">
    @include('admin.settings._tabs', ['active' => 'integrations'])

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Partner stack</p>
            <p class="text-sm text-white/85 mt-1">Open a partner for Configuration and Usage &amp; billing. Add more PSPs anytime.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.settings.integrations.partners.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-white text-brand text-xs font-bold px-4 py-2.5 hover:bg-brand-muted shadow-sm">
                Add partner
            </a>
            <form method="POST" action="{{ route('admin.settings.integrations.health') }}">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand text-xs font-bold px-4 py-2.5 hover:brightness-95 shadow-sm">
                    Check all health
                </button>
            </form>
        </div>
    </div>

    <section id="live-test" class="mb-8 overflow-hidden rounded-3xl bg-white ring-1 ring-brand/10 shadow-sm" x-data="{ suite: 'payment' }">
        <div class="bg-gradient-to-br from-slate-950 via-brand to-brand-light px-6 py-6 text-white">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">Integration sandbox</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight">Live test</h2>
                    <p class="mt-2 text-sm text-white/75 max-w-xl">Run real calls against payments, messaging, and CRB — same rails the product uses. Health check proves the wire; live test proves the flow.</p>
                </div>
                <div class="inline-flex rounded-2xl bg-white/10 p-1 ring-1 ring-white/15 backdrop-blur">
                    <button type="button" @click="suite = 'payment'" class="rounded-xl px-3.5 py-2 text-xs font-bold transition" :class="suite === 'payment' ? 'bg-brand-gold text-brand shadow' : 'text-white/80 hover:bg-white/10'">Payment</button>
                    <button type="button" @click="suite = 'messaging'" class="rounded-xl px-3.5 py-2 text-xs font-bold transition" :class="suite === 'messaging' ? 'bg-brand-gold text-brand shadow' : 'text-white/80 hover:bg-white/10'">Messaging</button>
                    <button type="button" @click="suite = 'crb'" class="rounded-xl px-3.5 py-2 text-xs font-bold transition" :class="suite === 'crb' ? 'bg-brand-gold text-brand shadow' : 'text-white/80 hover:bg-white/10'">CRB</button>
                </div>
            </div>
        </div>

        <div class="p-6 grid lg:grid-cols-2 gap-6">
            <form method="POST" action="{{ route('admin.settings.integrations.live-test') }}" class="space-y-4" x-show="suite === 'payment'" x-cloak>
                @csrf
                <input type="hidden" name="suite" value="payment">
                <p class="text-sm text-gray-600">Creates a test mobile-money payment for any phone number and opens the shared <span class="font-semibold text-gray-900">payments.show</span> gate preview. The number does not need to be a registered member.</p>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Phone number</label>
                    <input name="phone" required placeholder="2557…" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Amount (TZS)</label>
                    <input name="amount" type="number" min="500" step="100" value="1000" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20">
                </div>
                <button class="inline-flex items-center justify-center rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light shadow-sm">
                    Run payment live test
                </button>
            </form>

            <form method="POST" action="{{ route('admin.settings.integrations.live-test') }}" class="space-y-4" x-show="suite === 'messaging'" x-cloak>
                @csrf
                <input type="hidden" name="suite" value="messaging">
                <p class="text-sm text-gray-600">Sends a short SMS through the active gateway (or log driver if force-log is on).</p>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Phone</label>
                    <input name="phone" required placeholder="2557…" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Message</label>
                    <textarea name="message" rows="3" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20" placeholder="Optional custom test message"></textarea>
                </div>
                <button class="inline-flex items-center justify-center rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light shadow-sm">
                    Send messaging live test
                </button>
            </form>

            <form method="POST" action="{{ route('admin.settings.integrations.live-test') }}" class="space-y-4" x-show="suite === 'crb'" x-cloak>
                @csrf
                <input type="hidden" name="suite" value="crb">
                <p class="text-sm text-gray-600">Pulls identity from the configured CRB driver (stub or live) and returns match feedback.</p>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">NIDA</label>
                    <input name="nida" placeholder="Leave blank for sample NIDA" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20">
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Full name</label>
                        <input name="full_name" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Date of birth</label>
                        <input name="date_of_birth" placeholder="YYYY-MM-DD" class="w-full rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20">
                    </div>
                </div>
                <button class="inline-flex items-center justify-center rounded-xl bg-brand text-white text-sm font-bold px-5 py-3 hover:bg-brand-light shadow-sm">
                    Run CRB live test
                </button>
            </form>

            <div class="rounded-2xl bg-gradient-to-br from-brand-muted/60 to-white ring-1 ring-brand/10 p-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">How to use</p>
                <ul class="mt-3 space-y-2.5 text-sm text-gray-700">
                    <li class="flex gap-2"><span class="mt-1 size-1.5 rounded-full bg-brand-gold shrink-0"></span><span><strong>Health check</strong> — credentials &amp; reachability.</span></li>
                    <li class="flex gap-2"><span class="mt-1 size-1.5 rounded-full bg-brand-gold shrink-0"></span><span><strong>Live test</strong> — real payment gate, SMS send, or CRB pull.</span></li>
                    <li class="flex gap-2"><span class="mt-1 size-1.5 rounded-full bg-brand-gold shrink-0"></span><span>Payment tests need a phone + amount only (member optional); results open in the feedback modal with gate links.</span></li>
                </ul>
                @if (session('live_test_result.payment_id'))
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('admin.settings.integrations.live-test.payment', session('live_test_result.payment_id')) }}"
                           class="inline-flex rounded-xl bg-brand-gold text-brand text-xs font-bold px-4 py-2.5 hover:brightness-95">
                            Open payment gate preview
                        </a>
                        <a href="{{ route('admin.payments.show', session('live_test_result.payment_id')) }}"
                           class="inline-flex rounded-xl ring-1 ring-brand/20 text-brand text-xs font-bold px-4 py-2.5 hover:bg-brand-muted/40">
                            Open admin payment
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="space-y-6">
        @foreach ($groups as $category => $group)
            <section class="bg-white rounded-2xl ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-brand uppercase tracking-widest">{{ $group['meta']['label'] ?? $category }}</h2>
                        <p class="text-xs text-gray-500 mt-1">{{ $group['meta']['description'] ?? '' }}</p>
                    </div>
                    @if (! empty($group['primary']))
                        <p class="text-xs text-gray-600">
                            Primary:
                            <span class="font-semibold text-gray-900">{{ collect($group['partners'])->firstWhere('key', $group['primary'])['label'] ?? $group['primary'] }}</span>
                        </p>
                    @endif
                </div>

                <ul class="divide-y divide-gray-100">
                    @forelse ($group['partners'] as $partner)
                        @php
                            $health = $partner['health'] ?? [];
                            $unknown = ! empty($health['unknown']);
                            $ok = ! empty($health['ok']);
                            $channels = $partner['channels'] ?? [];
                        @endphp
                        <li class="px-5 py-4 flex flex-col lg:flex-row lg:items-center gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.settings.integrations.partner', $partner['key']) }}" class="text-sm font-semibold text-gray-900 hover:text-brand">
                                        {{ $partner['label'] }}
                                    </a>
                                    @if (! empty($partner['is_primary']))
                                        <span class="inline-flex items-center rounded-full bg-brand-muted text-brand text-[10px] font-bold uppercase tracking-wide px-2 py-0.5">Primary</span>
                                    @endif
                                    @foreach ($channels as $ch)
                                        <span class="inline-flex items-center rounded-full bg-sky-50 text-sky-800 text-[10px] font-semibold px-2 py-0.5">{{ $ch === 'mobile_money' ? 'Mobile money' : 'Bank' }}</span>
                                    @endforeach
                                </div>
                                <p class="text-xs text-gray-500 mt-1">{{ $partner['description'] ?? '' }}</p>
                                <div class="mt-3 max-w-md">
                                    <x-admin.integration-health-summary :partner-key="$partner['key']" :summary="app(\App\Services\Integrations\IntegrationFeedback::class)->persistentSummary($partner['key'])" />
                                </div>
                                @if (! $ok && ! $unknown && ! empty($partner['guidance']))
                                    <ul class="mt-2 text-xs text-rose-700 list-disc pl-4 space-y-0.5">
                                        @foreach (array_slice($partner['guidance'], 0, 2) as $tip)
                                            <li>{{ $tip }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                @if (empty($partner['is_primary']) && ($partner['status'] ?? '') === 'available')
                                    <form method="POST" action="{{ route('admin.settings.integrations.primary') }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="category" value="{{ $category }}">
                                        <input type="hidden" name="partner" value="{{ $partner['key'] }}">
                                        <button type="submit" class="rounded-xl ring-1 ring-gray-200 bg-white text-xs font-semibold text-gray-700 px-3 py-2 hover:bg-gray-50">
                                            Make primary
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.settings.integrations.health') }}">
                                    @csrf
                                    <input type="hidden" name="partner" value="{{ $partner['key'] }}">
                                    <button type="submit" class="rounded-xl bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light">
                                        Check health
                                    </button>
                                </form>
                                <a href="{{ route('admin.settings.integrations') }}#live-test"
                                   class="rounded-xl ring-1 ring-brand/20 text-brand text-xs font-semibold px-3 py-2 hover:bg-brand-muted/40">
                                    Live test
                                </a>
                                <a href="{{ route('admin.settings.integrations.partner', $partner['key']) }}"
                                   class="rounded-xl bg-brand-gold text-brand text-xs font-bold px-3 py-2 hover:brightness-95">
                                    Open
                                </a>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-sm text-gray-500">No partners yet. Add one to get started.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</x-admin.layout>
