@php
    $has = (bool) ($dossier['has_legal_signature'] ?? false);
    $data = $customer->legal_signature_data;
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Signature</p>
            <h4 class="text-base font-bold text-gray-900 mt-0.5">Member legal signature</h4>
            <p class="text-xs text-gray-500 mt-0.5">Used when the borrower signs offers and contracts.</p>
        </div>
        <span @class([
            'rounded-full px-2.5 py-1 text-[11px] font-bold',
            'bg-emerald-100 text-emerald-800' => $has,
            'bg-amber-100 text-amber-900' => ! $has,
        ])>{{ $has ? 'On file' : 'Missing' }}</span>
    </div>

    @if ($has && filled($data))
        <div class="rounded-2xl ring-1 ring-brand/10 bg-white p-4 max-w-md">
            <img src="{{ $data }}" alt="Member signature" class="h-24 object-contain bg-white">
            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wider text-gray-500">Signer name</dt>
                    <dd class="font-medium mt-0.5">{{ $customer->legal_signer_name ?: $customer->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-gray-500">Signed at</dt>
                    <dd class="font-medium mt-0.5">{{ optional($customer->legal_signed_at)->format('d M Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    @else
        <p class="text-sm text-gray-500">No profile signature captured yet.</p>
    @endif
</div>
