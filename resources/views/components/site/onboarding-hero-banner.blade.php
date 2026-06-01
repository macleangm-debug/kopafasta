@props(['banner' => []])

@if ($banner['show'] ?? false)
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-indigo-700 via-indigo-800 to-slate-900 text-white p-6 sm:p-8 shadow-lg">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="min-w-0 flex-1">
                <p class="text-xs uppercase tracking-widest text-indigo-200 font-semibold">Onboarding</p>
                <h2 class="text-xl sm:text-2xl font-bold mt-1">{{ $banner['title'] ?? 'Complete your profile' }} — {{ $banner['percent'] ?? 0 }}% complete</h2>
                <div class="mt-4 h-2 rounded-full bg-white/20 overflow-hidden max-w-md">
                    <div class="h-full bg-emerald-400 transition-all" style="width: {{ $banner['percent'] ?? 0 }}%"></div>
                </div>
                <ul class="mt-5 space-y-2.5">
                    @foreach (($banner['items'] ?? []) as $item)
                        @php
                            $status = $item['status'] ?? 'missing';
                            $icon = match ($status) {
                                'complete' => '✓',
                                'pending'  => '⏳',
                                default    => '⏳',
                            };
                            $suffix = match ($status) {
                                'complete' => '',
                                'pending'  => ' pending',
                                default    => ' missing',
                            };
                        @endphp
                        <li class="flex items-center gap-3 text-sm">
                            <span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold shrink-0
                                {{ $status === 'complete' ? 'bg-emerald-400 text-emerald-950' : 'bg-white/15 text-white' }}">
                                {{ $icon }}
                            </span>
                            <span class="{{ $status === 'complete' ? 'text-indigo-100 line-through' : 'text-white' }}">
                                {{ ucfirst($item['label'] ?? '') }}{{ $suffix }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if (! empty($banner['cta_url']))
                <a href="{{ $banner['cta_url'] }}"
                   class="shrink-0 self-start inline-flex bg-amber-400 hover:bg-amber-300 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                    Complete requirements
                </a>
            @endif
        </div>
    </div>
@endif
