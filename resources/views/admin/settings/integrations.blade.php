<x-admin.layout title="Integrations" heading="Integrations" subheading="Payment, SMS, email, and compliance partners — pick a primary, check health, add partners">
    @include('admin.settings._tabs', ['active' => 'integrations'])

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Partner stack</p>
            <p class="text-sm text-white/85 mt-1">Add multiple partners of the same type. Set one primary per category. Unhealthy checks show what to fix.</p>
        </div>
        <form method="POST" action="{{ route('admin.settings.integrations.health') }}">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand text-xs font-bold px-4 py-2.5 hover:brightness-95 shadow-sm">
                Check all health
            </button>
        </form>
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
                            $comingSoon = ($partner['status'] ?? '') === 'coming_soon';
                            $channels = $partner['channels'] ?? [];
                        @endphp
                        <li class="px-5 py-4 space-y-3">
                            <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-gray-900">{{ $partner['label'] }}</p>
                                        @if (! empty($partner['is_primary']))
                                            <span class="inline-flex items-center rounded-full bg-brand-muted text-brand text-[10px] font-bold uppercase tracking-wide px-2 py-0.5">Primary</span>
                                        @endif
                                        @if ($comingSoon)
                                            <span class="inline-flex items-center rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5">Coming soon</span>
                                        @endif
                                        @foreach ($channels as $ch)
                                            <span class="inline-flex items-center rounded-full bg-sky-50 text-sky-800 text-[10px] font-semibold px-2 py-0.5">{{ $ch === 'mobile_money' ? 'Mobile money' : 'Bank' }}</span>
                                        @endforeach
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">{{ $partner['description'] ?? '' }}</p>
                                    <p class="mt-2 text-xs {{ $comingSoon ? 'text-gray-400' : ($unknown ? 'text-amber-700' : ($ok ? 'text-emerald-700' : 'text-rose-700')) }}">
                                        @if ($comingSoon)
                                            Not connected yet
                                        @elseif ($unknown)
                                            Health not checked yet — run Check health
                                        @else
                                            {{ $ok ? 'Healthy' : 'Unhealthy' }}
                                            @if (! empty($health['message'])) — {{ $health['message'] }} @endif
                                            @if (! empty($health['checked_at']))
                                                <span class="text-gray-400">· {{ \Illuminate\Support\Carbon::parse($health['checked_at'])->diffForHumans() }}</span>
                                            @endif
                                        @endif
                                    </p>
                                    @if (! $ok && ! $unknown && ! empty($partner['guidance']))
                                        <ul class="mt-2 text-xs text-rose-700 list-disc pl-4 space-y-0.5">
                                            @foreach ($partner['guidance'] as $tip)
                                                <li>{{ $tip }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    @if (! $comingSoon && empty($partner['is_primary']))
                                        <form method="POST" action="{{ route('admin.settings.integrations.primary') }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="category" value="{{ $category }}">
                                            <input type="hidden" name="partner" value="{{ $partner['key'] }}">
                                            <button type="submit" class="rounded-xl ring-1 ring-gray-200 bg-white text-xs font-semibold text-gray-700 px-3 py-2 hover:bg-gray-50">
                                                Make primary
                                            </button>
                                        </form>
                                    @endif

                                    @if (! $comingSoon)
                                        <form method="POST" action="{{ route('admin.settings.integrations.health') }}">
                                            @csrf
                                            <input type="hidden" name="partner" value="{{ $partner['key'] }}">
                                            <button type="submit" class="rounded-xl bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light">
                                                Check health
                                            </button>
                                        </form>
                                    @endif

                                    @if (! empty($partner['settings_route']))
                                        <a href="{{ route($partner['settings_route']) }}"
                                           class="rounded-xl bg-brand-gold text-brand text-xs font-bold px-3 py-2 hover:brightness-95">
                                            Configure
                                        </a>
                                    @endif
                                </div>
                            </div>

                            @if ($category === 'payment' && ! $comingSoon)
                                <form method="POST" action="{{ route('admin.settings.integrations.channels') }}" class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3 flex flex-wrap items-center gap-3">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="partner" value="{{ $partner['key'] }}">
                                    <span class="text-xs font-semibold text-gray-600">Rails:</span>
                                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-700">
                                        <input type="checkbox" name="channels[]" value="mobile_money" @checked(in_array('mobile_money', $channels, true)) class="size-3.5 rounded border-gray-300 text-brand">
                                        Mobile money
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-700">
                                        <input type="checkbox" name="channels[]" value="bank" @checked(in_array('bank', $channels, true)) class="size-3.5 rounded border-gray-300 text-brand">
                                        Bank transfer
                                    </label>
                                    <button type="submit" class="ml-auto text-xs font-semibold text-brand hover:underline">Save rails</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-sm text-gray-500">No partners in this category yet.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>

    <section class="mt-6 bg-white rounded-2xl ring-1 ring-brand/10 shadow-sm p-5">
        <h2 class="text-sm font-bold text-brand uppercase tracking-widest">Add partner</h2>
        <p class="text-xs text-gray-500 mt-1 mb-4">Register another PSP or messaging provider. For payment partners, choose mobile money, bank, or both.</p>
        <form method="POST" action="{{ route('admin.settings.integrations.partners.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <x-admin.input name="label" label="Partner name" placeholder="e.g. Selcom live" required />
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Category</label>
                <select name="category" class="w-full text-sm border border-brand/15 rounded-xl px-3.5 py-2.5" required>
                    @foreach (config('integrations.categories', []) as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] ?? $key }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <x-admin.input name="description" label="Description (optional)" placeholder="Short note for admins" />
            </div>
            <x-admin.input name="docs_url" label="Docs URL (optional)" placeholder="https://…" />
            <div>
                <p class="block text-xs font-semibold text-gray-700 mb-2">Payment rails (if Payments)</p>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="channels[]" value="mobile_money" class="size-4 rounded border-gray-300 text-brand" checked>
                        Mobile money
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="channels[]" value="bank" class="size-4 rounded border-gray-300 text-brand">
                        Bank transfer
                    </label>
                </div>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm">
                    Add partner
                </button>
            </div>
        </form>
    </section>
</x-admin.layout>
