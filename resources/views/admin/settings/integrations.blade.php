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

    <section id="live-test" class="mb-8 overflow-hidden rounded-3xl bg-white ring-1 ring-brand/10 shadow-sm">
        <div class="bg-gradient-to-br from-slate-950 via-brand to-brand-light px-6 py-6 text-white">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">Integration sandbox</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight">Live test</h2>
                    <p class="mt-2 text-sm text-white/75 max-w-xl">Health check proves credentials. Live test opens a focused modal/sheet on the partner page — never an inline form on the profile.</p>
                </div>
            </div>
        </div>

        <div class="p-6 grid lg:grid-cols-2 gap-6">
            <div class="rounded-2xl bg-gradient-to-br from-brand-muted/60 to-white ring-1 ring-brand/10 p-5 space-y-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Action meanings</p>
                <ul class="space-y-2.5 text-sm text-gray-700">
                    <li><strong>Save settings</strong> — persists configuration only. Connection remains Not tested.</li>
                    <li><strong>Save &amp; test connection / Check health</strong> — genuine non-transactional auth/reachability probe.</li>
                    <li><strong>Live test</strong> — deliberate operational rehearsal in a modal/sheet (review → confirm → execute).</li>
                </ul>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 space-y-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Open provider live test</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.settings.integrations.partner', ['partner' => 'payin', 'live_test' => 1]) }}" class="rounded-xl bg-brand text-white text-xs font-bold px-4 py-2.5">PayIn live test</a>
                    <a href="{{ route('admin.settings.integrations.partner', ['partner' => 'unitxt', 'live_test' => 1]) }}" class="rounded-xl ring-1 ring-brand/20 text-brand text-xs font-bold px-4 py-2.5">Unitxt SMS</a>
                    <a href="{{ route('admin.settings.integrations.partner', ['partner' => 'email_smtp', 'live_test' => 1]) }}" class="rounded-xl ring-1 ring-brand/20 text-brand text-xs font-bold px-4 py-2.5">Email SMTP</a>
                    <a href="{{ route('admin.settings.integrations.partner', ['partner' => 'crb', 'live_test' => 1]) }}" class="rounded-xl ring-1 ring-brand/20 text-brand text-xs font-bold px-4 py-2.5">CRB</a>
                </div>
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
