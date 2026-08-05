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
                                <p class="mt-2 text-xs {{ $unknown ? 'text-amber-700' : ($ok ? 'text-emerald-700' : 'text-rose-700') }}">
                                    @if ($unknown)
                                        Health not checked yet
                                    @else
                                        {{ $ok ? 'Healthy' : 'Unhealthy' }}
                                        @if (! empty($health['message'])) — {{ $health['message'] }} @endif
                                    @endif
                                </p>
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
