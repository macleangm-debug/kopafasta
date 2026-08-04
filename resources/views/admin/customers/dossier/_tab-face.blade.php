@php
    $faceSteps = app(\App\Services\FaceVerificationService::class)->wizardSteps($customer);
@endphp

<p class="text-sm text-gray-500 mb-4">Click a photo to enlarge. Face decisions stay on the loan application under screening.</p>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse ($faceSteps as $step)
        <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden bg-gray-50">
            <p class="px-3 py-2 text-[10px] uppercase tracking-widest font-semibold text-gray-500 border-b border-gray-100">{{ $step['label'] ?? $step['key'] }}</p>
            @if ($step['previewUrl'] ?? null)
                <button type="button"
                        onclick="window.kfOpenDocumentPreview(@js($step['previewUrl']), @js($step['label'] ?? 'Face'), 'image')"
                        class="block w-full text-left group">
                    <img src="{{ $step['previewUrl'] }}" alt="{{ $step['label'] ?? 'Face' }}"
                         class="w-full h-48 object-cover group-hover:opacity-95 transition cursor-zoom-in">
                </button>
            @else
                <div class="h-48 grid place-items-center text-sm text-gray-400">Not uploaded</div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 sm:col-span-2">No face captures on file.</p>
    @endforelse

    @if ($dossier['id_photo_url'] ?? null)
        <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden bg-gray-50">
            <p class="px-3 py-2 text-[10px] uppercase tracking-widest font-semibold text-gray-500 border-b border-gray-100">ID card</p>
            <button type="button"
                    onclick="window.kfOpenDocumentPreview(@js($dossier['id_photo_url']), 'ID card', 'image')"
                    class="block w-full text-left group">
                <img src="{{ $dossier['id_photo_url'] }}" alt="ID card"
                     class="w-full h-48 object-cover group-hover:opacity-95 transition cursor-zoom-in">
            </button>
        </div>
    @endif
</div>
