@php
    $identityDocs = collect($dossier['documents_by_context']['identity'] ?? []);
    $idFront = $identityDocs->first(fn ($d) => str_contains(strtolower((string) ($d->documentType?->code ?? '')), 'national_id_front'));
    $idBack = $identityDocs->first(fn ($d) => str_contains(strtolower((string) ($d->documentType?->code ?? '')), 'national_id_back'));
    if (! $idFront) {
        $idFront = $identityDocs->first(fn ($d) => in_array((string) ($d->documentType?->code ?? ''), ['passport', 'voter_id', 'driving_license'], true));
    }
    $faceSteps = app(\App\Services\FaceVerificationService::class)->wizardSteps($customer);
    $hasSignature = (bool) ($dossier['has_legal_signature'] ?? false);
    $signatureData = $customer->legal_signature_data;
@endphp

<div class="space-y-8">
    <section>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">Personal details</p>
        <dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Full name</dt><dd class="font-medium mt-0.5">{{ $customer->full_name ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Date of birth</dt><dd class="font-medium mt-0.5">{{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Gender</dt><dd class="font-medium mt-0.5">{{ ucfirst($customer->gender ?? '—') }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Marital status</dt><dd class="font-medium mt-0.5">{{ $customer->marital_status ? ucfirst(str_replace('_', ' ', (string) $customer->marital_status)) : '—' }}</dd></div>
            @if (filled($customer->number_of_children) || $customer->number_of_children === 0 || $customer->number_of_children === '0')
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Children</dt><dd class="font-medium mt-0.5">{{ $customer->number_of_children }}</dd></div>
            @endif
            @if (filled($customer->spouse_first_name) || filled($customer->spouse_last_name))
                <div class="md:col-span-2">
                    <dt class="text-xs uppercase tracking-wider text-gray-500">Spouse</dt>
                    <dd class="font-medium mt-0.5">{{ trim(collect([$customer->spouse_first_name, $customer->spouse_middle_name, $customer->spouse_last_name])->filter()->implode(' ')) }}</dd>
                </div>
            @endif
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Phone</dt><dd class="font-medium mt-0.5">{{ $customer->phone ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Email</dt><dd class="font-medium mt-0.5">{{ $customer->email ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">National ID (NIDA)</dt><dd class="font-medium mt-0.5 font-mono">{{ $customer->national_id ?: '—' }}</dd></div>
            <div>
                <dt class="text-xs uppercase tracking-wider text-gray-500">Identity / NIDA</dt>
                <dd class="font-medium mt-0.5">
                    @if ($dossier['nida_verified'] ?? false)
                        <span class="text-emerald-700">Verified</span>
                    @else
                        <span class="text-amber-800">Not verified</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wider text-gray-500">Face</dt>
                <dd class="font-medium mt-0.5">
                    @if ($dossier['face_verified'] ?? false)
                        <span class="text-emerald-700">Verified</span>
                    @else
                        <span class="text-amber-800">{{ ($dossier['face_progress']['percent'] ?? 0) }}% captured</span>
                    @endif
                </dd>
            </div>
        </dl>
        @if ($customer->identity_locked)
            <p class="mt-4 text-xs text-amber-700 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">Identity locked after NIDA verification.</p>
        @endif
    </section>

    <section class="border-t border-gray-100 pt-6">
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">National ID</p>
        <div class="grid grid-cols-2 gap-3 max-w-md">
            @foreach ([
                ['label' => 'Front', 'doc' => $idFront],
                ['label' => 'Back', 'doc' => $idBack],
            ] as $side)
                @php
                    $doc = $side['doc'];
                    $url = ($doc && $doc->file_path) ? asset('storage/'.$doc->file_path) : null;
                @endphp
                <div class="rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
                    <p class="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">{{ $side['label'] }}</p>
                    @if ($url)
                        <button type="button"
                                onclick="window.kfOpenDocumentPreview(@js($url), @js('National ID — '.$side['label']), 'image')"
                                class="block w-full text-left">
                            <div class="aspect-[3/2] bg-gray-50">
                                <img src="{{ $url }}" alt="National ID {{ $side['label'] }}" class="size-full object-cover">
                            </div>
                        </button>
                    @else
                        <div class="aspect-[3/2] grid place-items-center bg-gray-50">
                            <p class="text-xs text-gray-400">Missing</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-t border-gray-100 pt-6">
        <div class="flex items-baseline justify-between gap-3 mb-3">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Face / identity captures</p>
            <p class="text-[11px] text-gray-500 tabular-nums">{{ collect($faceSteps)->where(fn ($s) => filled($s['previewUrl'] ?? null))->count() }}/{{ max(1, count($faceSteps)) }}</p>
        </div>
        <div class="grid grid-cols-4 gap-2 max-w-lg">
            @foreach ($faceSteps as $step)
                @php $hasPhoto = filled($step['previewUrl'] ?? null); @endphp
                <div class="rounded-lg ring-1 ring-gray-200 bg-white overflow-hidden">
                    <p class="px-1.5 py-1 text-[9px] font-bold uppercase tracking-wider text-gray-500 truncate">{{ $step['label'] ?? $step['key'] }}</p>
                    @if ($hasPhoto)
                        <button type="button"
                                onclick="window.kfOpenDocumentPreview(@js($step['previewUrl']), @js($step['label'] ?? 'Face'), 'image')"
                                class="block w-full">
                            <div class="aspect-square bg-gray-50">
                                <img src="{{ $step['previewUrl'] }}" alt="{{ $step['label'] ?? 'Face' }}" class="size-full object-cover">
                            </div>
                        </button>
                    @else
                        <div class="aspect-square grid place-items-center bg-gray-50">
                            <p class="text-[10px] text-gray-400">Missing</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-t border-gray-100 pt-6">
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">Next of kin / family</p>
        @include('admin.customers.dossier._tab-kin')
    </section>

    <section class="border-t border-gray-100 pt-6">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Signature</p>
            <span @class([
                'rounded-full px-2.5 py-0.5 text-[11px] font-bold',
                'bg-emerald-100 text-emerald-800' => $hasSignature,
                'bg-amber-100 text-amber-900' => ! $hasSignature,
            ])>{{ $hasSignature ? 'On file' : 'Missing' }}</span>
        </div>
        <p class="text-xs text-gray-500 mb-3 max-w-lg">This is the signature stored on the member’s profile. A document that has already been issued keeps the signature captured on that document. It does not change if the member later replaces this profile signature.</p>
        @if ($hasSignature && filled($signatureData))
            <div class="max-w-sm rounded-xl ring-1 ring-gray-100 bg-[linear-gradient(45deg,#f3f4f6_25%,transparent_25%),linear-gradient(-45deg,#f3f4f6_25%,transparent_25%),linear-gradient(45deg,transparent_75%,#f3f4f6_75%),linear-gradient(-45deg,transparent_75%,#f3f4f6_75%)] bg-[length:12px_12px] bg-[position:0_0,0_6px,6px_-6px,-6px_0] p-3">
                <img src="{{ $signatureData }}" alt="Member signature" class="h-16 object-contain">
            </div>
            <dl class="mt-3 grid sm:grid-cols-2 gap-3 text-sm max-w-md">
                <div>
                    <dt class="text-xs uppercase tracking-wider text-gray-500">Signer name</dt>
                    <dd class="font-medium mt-0.5">{{ $customer->legal_signer_name ?: $customer->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wider text-gray-500">Signed at</dt>
                    <dd class="font-medium mt-0.5">{{ optional($customer->legal_signed_at)->format('d M Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
        @else
            <p class="text-sm text-gray-500">No profile signature captured yet.</p>
        @endif
    </section>
</div>
