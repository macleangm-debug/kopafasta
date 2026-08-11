@php
    $faceSteps = app(\App\Services\FaceVerificationService::class)->wizardSteps($customer);
    $doneCount = collect($faceSteps)->where('done', true)->count();
    $totalSteps = count($faceSteps);
    $idUrl = $dossier['id_photo_url'] ?? null;
@endphp

<div class="space-y-5">
    <div class="rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-gold">Identity capture</p>
                <h4 class="text-lg font-bold mt-1">Face gallery</h4>
                <p class="text-sm text-white/80 mt-1">Click any photo to enlarge. Verification decisions stay on the loan application.</p>
            </div>
            <p class="text-sm font-semibold tabular-nums bg-white/10 ring-1 ring-white/20 rounded-xl px-3 py-1.5">
                {{ $doneCount }}/{{ max(1, $totalSteps) }} poses on file
            </p>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($faceSteps as $step)
            @php
                $hasPhoto = filled($step['previewUrl'] ?? null);
            @endphp
            <article @class([
                'group relative overflow-hidden rounded-2xl shadow-sm ring-1',
                'ring-brand/20 bg-white' => $hasPhoto,
                'ring-dashed ring-gray-200 bg-gray-50' => ! $hasPhoto,
            ])>
                <div class="absolute inset-x-0 top-0 z-10 flex items-center justify-between gap-2 px-3 py-2.5 bg-gradient-to-b from-black/55 to-transparent">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-white drop-shadow">
                        {{ $step['label'] ?? $step['key'] }}
                    </p>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-[10px] font-bold',
                        'bg-emerald-400/90 text-emerald-950' => $hasPhoto,
                        'bg-white/90 text-gray-600' => ! $hasPhoto,
                    ])>
                        {{ $hasPhoto ? 'Captured' : 'Missing' }}
                    </span>
                </div>

                @if ($hasPhoto)
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js($step['previewUrl']), @js($step['label'] ?? 'Face'), 'image')"
                            class="block w-full text-left">
                        <div class="aspect-[4/5] overflow-hidden bg-brand-muted/30">
                            <img src="{{ $step['previewUrl'] }}"
                                 alt="{{ $step['label'] ?? 'Face' }}"
                                 class="size-full object-cover transition duration-300 group-hover:scale-[1.03] cursor-zoom-in">
                        </div>
                    </button>
                    <div class="px-3 py-2.5 flex items-center justify-between gap-2 border-t border-gray-100">
                        <p class="text-[11px] text-gray-500 truncate">{{ $step['instruction'] ?? 'Tap to preview' }}</p>
                        <button type="button"
                                onclick="window.kfOpenDocumentPreview(@js($step['previewUrl']), @js($step['label'] ?? 'Face'), 'image')"
                                class="shrink-0 text-[11px] font-bold text-brand hover:underline">
                            Enlarge
                        </button>
                    </div>
                @else
                    <div class="aspect-[4/5] grid place-items-center px-4 text-center">
                        <div>
                            <p class="text-sm font-semibold text-gray-500">Not uploaded</p>
                            <p class="text-xs text-gray-400 mt-1">Borrower captures this in the app.</p>
                        </div>
                    </div>
                @endif
            </article>
        @empty
            <p class="text-sm text-gray-500 sm:col-span-2 lg:col-span-3 py-8 text-center">No face captures on file.</p>
        @endforelse

        @if ($idUrl)
            <article class="group relative overflow-hidden rounded-2xl shadow-sm ring-1 ring-brand/20 bg-white">
                <div class="absolute inset-x-0 top-0 z-10 flex items-center justify-between gap-2 px-3 py-2.5 bg-gradient-to-b from-black/55 to-transparent">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-white drop-shadow">ID card</p>
                    <span class="rounded-full bg-brand-gold/95 px-2 py-0.5 text-[10px] font-bold text-brand">On file</span>
                </div>
                <button type="button"
                        onclick="window.kfOpenDocumentPreview(@js($idUrl), 'ID card', 'image')"
                        class="block w-full text-left">
                    <div class="aspect-[4/5] overflow-hidden bg-brand-muted/30">
                        <img src="{{ $idUrl }}" alt="ID card"
                             class="size-full object-cover transition duration-300 group-hover:scale-[1.03] cursor-zoom-in">
                    </div>
                </button>
                <div class="px-3 py-2.5 border-t border-gray-100">
                    <p class="text-[11px] text-gray-500">National ID / identity document preview</p>
                </div>
            </article>
        @endif
    </div>
</div>
