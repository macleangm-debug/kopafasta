@props([
    'assets',
    'availabilities',
    'application',
    'typeIcons' => [],
])

@php
    $assets = collect($assets ?? []);
    $availabilities = collect($availabilities ?? []);
    $typeIcons = $typeIcons ?: \App\Models\CustomerAsset::typeIcons();
@endphp

@if ($assets->isNotEmpty())
    <div class="space-y-2">
        @foreach ($assets as $asset)
            @php
                $availability = $availabilities[$asset->id] ?? ['code' => 'available', 'selectable' => false];
                $viewUrl = route('site.borrower.profile', array_filter([
                    'section' => 'assets',
                    'view' => $asset->id,
                    'uw' => 1,
                    'application' => $application->id,
                ]));
            @endphp
            <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="size-14 rounded-xl overflow-hidden bg-brand-muted/40 ring-1 ring-brand/10 shrink-0 grid place-items-center">
                            @if ($asset->thumbnailPath())
                                <img src="{{ asset('storage/'.$asset->thumbnailPath()) }}" alt="" class="size-full object-cover">
                            @else
                                <span class="text-lg">{{ $typeIcons[$asset->asset_type] ?? '📦' }}</span>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ $asset->label }}
                            </p>
                            <p class="text-[11px] text-gray-500">{{ __('borrower.profile.asset_types.'.$asset->asset_type) }}</p>
                            @include('site.borrower.profile._asset_availability', ['availability' => $availability, 'showHint' => true])
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        @if ($availability['selectable'] ?? false)
                            <form method="POST" action="{{ route('site.borrower.profile.assets.use', $asset) }}">
                                @csrf
                                <input type="hidden" name="application_id" value="{{ $application->id }}">
                                <button type="submit"
                                        class="inline-flex items-center justify-center rounded-xl bg-brand-gold px-3 py-2 text-xs font-bold text-brand shadow-sm hover:brightness-95">
                                    {{ __('borrower.profile.collateral_use_this') }}
                                </button>
                            </form>
                        @endif
                        <a href="{{ $viewUrl }}"
                           class="inline-flex items-center justify-center rounded-xl bg-white px-3 py-2 text-xs font-bold text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40">
                            {{ __('borrower.profile.view_asset') }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<a href="{{ route('site.borrower.profile', array_filter([
        'section' => 'assets',
        'add' => 1,
        'uw' => 1,
        'application' => $application->id,
    ])) }}"
   @class([
       'inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-bold shadow-sm sm:w-auto',
       'bg-brand-gold text-brand hover:brightness-95' => $assets->isEmpty(),
       'bg-white text-brand ring-1 ring-brand/20 hover:bg-brand-muted/40' => $assets->isNotEmpty(),
   ])>
    {{ $assets->isEmpty() ? __('borrower.loan_profile.document_go_to_profile') : __('borrower.profile.add_new_collateral') }}
</a>
