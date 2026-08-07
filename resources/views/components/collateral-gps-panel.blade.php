@props([
    'items' => [],
    'title' => 'Collateral & GPS',
    'compact' => false,
    'installerContact' => null,
    'showInstallerContact' => false,
])

@php
    $gpsStatusLabels = [
        'secured' => 'GPS secured',
        'install_pending' => 'GPS install pending',
        'required' => 'GPS required',
        'not_required' => 'No GPS required',
    ];
    $mapEnabled = app(\App\Services\GpsDeviceService::class)->mapEnabled();
@endphp

@if (! empty($items) || ($showInstallerContact && ! empty($installerContact)))
    <div {{ $attributes->merge(['class' => $compact ? '' : 'rounded-xl ring-1 ring-gray-200 bg-white p-4']) }}>
        @unless ($compact)
            <h3 class="text-sm font-bold text-gray-900 mb-3">{{ $title }}</h3>
        @endunless

        @if (! empty($items))
            <ul class="space-y-3 text-sm">
                @foreach ($items as $item)
                    <li class="rounded-lg border border-gray-100 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $item['label'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ display_label($item['asset_type'] ?? '', 'asset_type') }}
                                    @if (! empty($item['registration_number']))
                                        · Plate {{ $item['registration_number'] }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-[11px] font-semibold px-2 py-1 rounded-full
                                {{ ($item['gps_status'] ?? '') === 'secured' ? 'bg-emerald-50 text-emerald-700' : (($item['gps_status'] ?? '') === 'not_required' ? 'bg-gray-50 text-gray-600' : 'bg-amber-50 text-amber-800') }}">
                                {{ $gpsStatusLabels[$item['gps_status'] ?? ''] ?? ($item['gps_status'] ?? '') }}
                            </span>
                        </div>
                        @if (! empty($item['gps_serial']) || ! empty($item['gps_provider']))
                            <p class="text-xs text-gray-600 mt-2 font-mono">
                                @if (! empty($item['gps_provider']))
                                    {{ $item['gps_provider'] }}
                                @endif
                                @if (! empty($item['gps_serial']))
                                    · {{ $item['gps_serial'] }}
                                @endif
                                @if (! empty($item['gps_device_id']))
                                    · ID {{ $item['gps_device_id'] }}
                                @endif
                            </p>
                        @endif
                        @if (! empty($item['can_view_asset']) && ! empty($item['tracking_url']))
                            <a href="{{ $item['tracking_url'] }}" target="_blank" rel="noopener"
                               class="inline-flex mt-3 rounded-lg bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light">
                                View Asset Location
                            </a>
                        @elseif ($mapEnabled && empty($item['tracking_url']) && in_array($item['gps_status'] ?? '', ['secured', 'required', 'install_pending'], true))
                            <p class="text-[11px] text-gray-500 mt-2">
                                No device tracking URL on this loan yet — the GPS installer must enter it at install.
                            </p>
                        @elseif (! $mapEnabled && filled($item['tracking_url'] ?? null))
                            <p class="text-[11px] text-gray-500 mt-2">
                                Map viewing is disabled in Recovery settings.
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($showInstallerContact && ! empty($installerContact))
            <div class="mt-4 rounded-lg border border-sky-100 bg-sky-50/60 p-3 text-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-900">GPS partner — deactivate device</p>
                <p class="mt-1 text-sky-950 font-semibold">{{ $installerContact['name'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-sky-900/80">
                    Call or message this GPS partner to deactivate the unit on their platform before / during repossession.
                </p>
                <dl class="mt-2 grid sm:grid-cols-2 gap-2 text-xs">
                    <div>
                        <dt class="text-sky-800/70">Phone</dt>
                        <dd class="font-semibold text-sky-950">
                            @if (! empty($installerContact['phone']))
                                <a href="tel:{{ $installerContact['phone'] }}" class="hover:underline">{{ $installerContact['phone'] }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sky-800/70">Email</dt>
                        <dd class="font-semibold text-sky-950">
                            @if (! empty($installerContact['email']))
                                <a href="mailto:{{ $installerContact['email'] }}" class="hover:underline">{{ $installerContact['email'] }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        @endif
    </div>
@endif
