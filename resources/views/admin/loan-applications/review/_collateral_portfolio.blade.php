@php
    $viewer = $viewer ?? 'screening';
    $portfolio = $portfolio
        ?? app(\App\Services\CollateralCardService::class)->portfolio($record, $viewer);
    $typeIcons = $typeIcons ?? \App\Models\CustomerAsset::typeIcons();
    $count = (int) ($portfolio['count'] ?? 0);
    $cards = collect($portfolio['cards'] ?? []);
    $previewLimit = (int) ($portfolio['preview_limit'] ?? \App\Services\CollateralCardService::PREVIEW_LIMIT);
    $viewAllUrl = $viewAllUrl ?? route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'profiles',
        'tab' => 'collateral',
        'person' => 'borrower',
    ]).'#borrower-file';
    $ratio = $portfolio['coverage_ratio'] ?? null;
@endphp

@if ($count > 0)
    <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Collateral</p>
            <p class="text-sm font-semibold text-gray-900 mt-0.5">
                {{ $count }} collateral asset{{ $count === 1 ? '' : 's' }}
                @if (($portfolio['total_fsv'] ?? 0) > 0)
                    · Total FSV {{ format_money($portfolio['total_fsv']) }}
                @endif
                @if (($portfolio['required_security'] ?? 0) > 0)
                    · Required security {{ format_money($portfolio['required_security']) }}
                @endif
                @if ($ratio)
                    · Coverage {{ number_format($ratio, 1) }}×
                @endif
            </p>
        </div>
        <div class="p-5 sm:p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($cards->take($count > $previewLimit ? $previewLimit : $count) as $card)
                    <x-site.collateral-card :selected="$card" :type-icons="$typeIcons" layout="grid">
                        <a href="{{ $viewAllUrl }}"
                           class="mt-3 inline-flex items-center justify-center w-full bg-gray-900 hover:bg-black text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                            View collateral
                        </a>
                    </x-site.collateral-card>
                @endforeach
            </div>
            @if ($count > $previewLimit)
                <a href="{{ $viewAllUrl }}" class="inline-flex text-sm font-semibold text-brand hover:underline">
                    View all collateral ({{ $count }})
                </a>
            @endif
        </div>
    </section>
@endif
