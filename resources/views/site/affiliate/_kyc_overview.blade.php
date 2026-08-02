@props(['vendor'])

@php
    $faces = $vendor->metadata['face_captures'] ?? [];
    $faceComplete = filled($faces['front'] ?? null)
        && filled($faces['left'] ?? null)
        && filled($faces['right'] ?? null)
        && filled($faces['holding_id'] ?? null);
    $hasSelfie = $faceComplete || filled($vendor->affiliate_selfie_path);
    $hasId = filled($vendor->affiliate_id_path);
    $hasContact = filled($vendor->name) && filled($vendor->phone);
    $hasPayout = ! empty($vendor->metadata['payout_account'] ?? null);

    $sections = [
        'contact' => [
            'label' => __('site.affiliate_portal.section_contact'),
            'hint'  => __('site.affiliate_portal.section_contact_hint'),
            'icon'  => '👤',
            'complete' => $hasContact,
            'required' => true,
        ],
        'selfie' => [
            'label' => __('site.affiliate_portal.face_section'),
            'hint'  => __('site.affiliate_portal.face_hint'),
            'icon'  => '🤳',
            'complete' => $hasSelfie,
            'required' => true,
        ],
        'id' => [
            'label' => __('site.affiliate_portal.national_id'),
            'hint'  => __('site.affiliate_portal.section_id_hint'),
            'icon'  => '🪪',
            'complete' => $hasId,
            'required' => true,
        ],
        'payment' => [
            'label' => __('site.partner_account.payment_section'),
            'hint'  => __('site.partner_account.payment_empty'),
            'icon'  => '💳',
            'complete' => $hasPayout,
            'required' => true,
        ],
    ];

    $requiredDone = collect($sections)->where('required', true)->every(fn ($s) => $s['complete']);
@endphp

<div class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-3">
    @foreach ($sections as $section)
        <div @class([
            'rounded-xl p-4 ring-1',
            'bg-emerald-50 ring-emerald-200' => $section['complete'],
            'bg-amber-50 ring-amber-200' => ! $section['complete'] && $section['required'],
            'bg-white ring-gray-200' => ! $section['complete'] && ! $section['required'],
        ])>
            <div class="flex items-start justify-between gap-2">
                <span class="text-xl" aria-hidden="true">{{ $section['icon'] }}</span>
                <span @class([
                    'text-[10px] font-bold uppercase tracking-wide rounded-full px-2 py-0.5',
                    'bg-emerald-100 text-emerald-800' => $section['complete'],
                    'bg-amber-100 text-amber-800' => ! $section['complete'] && $section['required'],
                    'bg-gray-100 text-gray-600' => ! $section['complete'] && ! $section['required'],
                ])>
                    {{ $section['complete'] ? __('site.affiliate_portal.status_complete') : ($section['required'] ? __('site.affiliate_portal.status_needs_work') : __('site.affiliate_portal.status_optional')) }}
                </span>
            </div>
            <p class="text-sm font-semibold text-gray-900 mt-2">{{ $section['label'] }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $section['hint'] }}</p>
        </div>
    @endforeach
</div>

@unless ($requiredDone)
    <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
        {{ __('site.affiliate_portal.kyc_pending_body') }}
    </div>
@endunless
