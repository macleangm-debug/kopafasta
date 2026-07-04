@props(['vendor'])

@php
    $kycApproved = in_array($vendor->affiliate_kyc_status, ['verified', 'approved'], true);
    $hasSelfie = filled($vendor->affiliate_selfie_path);
    $hasId = filled($vendor->affiliate_id_path);
    $hasPhoto = filled($vendor->affiliate_photo_path);
    $hasContact = filled($vendor->name) && filled($vendor->phone);

    $sections = [
        'contact' => [
            'label' => __('site.affiliate_portal.section_contact'),
            'hint'  => __('site.affiliate_portal.section_contact_hint'),
            'icon'  => '👤',
            'complete' => $hasContact,
            'required' => true,
        ],
        'selfie' => [
            'label' => __('site.affiliate_portal.selfie'),
            'hint'  => __('site.affiliate_portal.section_selfie_hint'),
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
        'photo' => [
            'label' => __('site.affiliate_portal.profile_photo'),
            'hint'  => __('site.affiliate_portal.section_photo_hint'),
            'icon'  => '📷',
            'complete' => $hasPhoto,
            'required' => false,
        ],
    ];

    $requiredSections = collect($sections)->filter(fn ($s) => $s['required']);
    $completedRequired = $requiredSections->filter(fn ($s) => $s['complete'])->count();
    $percent = $kycApproved ? 100 : (int) round(($completedRequired / max(1, $requiredSections->count())) * 100);

    $ringRadius = 52;
    $ringCircumference = 2 * M_PI * $ringRadius;
    $ringDashoffset = $ringCircumference - ($percent / 100) * $ringCircumference;

    $statusLabel = match (true) {
        $kycApproved => __('site.affiliate_portal.kyc_approved'),
        ($vendor->affiliate_kyc_status ?? '') === 'submitted' => __('site.affiliate_portal.kyc_submitted'),
        default => __('site.affiliate_portal.kyc_pending'),
    };
@endphp

<section class="mb-6 glass-card overflow-hidden relative">
    <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.35),_transparent_55%)] pointer-events-none"></div>

    <div class="relative p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-6">
            <div class="relative size-28 sm:size-32 shrink-0 mx-auto sm:mx-0">
                <svg class="w-full h-full -rotate-90" viewBox="0 0 128 128">
                    <circle cx="64" cy="64" r="{{ $ringRadius }}" stroke-width="10" class="stroke-gray-200/80" fill="none"></circle>
                    <circle cx="64" cy="64" r="{{ $ringRadius }}" stroke-width="10"
                            class="{{ $percent >= 100 ? 'stroke-emerald-500' : 'stroke-brand' }}"
                            fill="none" stroke-linecap="round"
                            stroke-dasharray="{{ format_number($ringCircumference, 2, '.', '') }}"
                            stroke-dashoffset="{{ format_number($ringDashoffset, 2, '.', '') }}"></circle>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-3xl font-bold text-gray-900 leading-none tabular-nums">{{ $percent }}%</span>
                    <span class="text-[10px] uppercase tracking-wide text-gray-500 mt-1">{{ __('site.affiliate_portal.kyc_progress') }}</span>
                </div>
            </div>
            <div class="flex-1 text-center sm:text-left">
                <h2 class="font-bold text-gray-900 text-lg">{{ __('site.affiliate_portal.kyc_hub_title') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_portal.kyc_hub_subtitle') }}</p>
                <p class="mt-3 inline-flex text-xs font-bold uppercase tracking-wide rounded-full px-3 py-1 ring-1
                    {{ $kycApproved ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-amber-100 text-amber-900 ring-amber-200' }}">
                    {{ $statusLabel }}
                </p>
            </div>
        </div>
    </div>
</section>

<section class="mb-6">
    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('site.affiliate_portal.kyc_sections') }}</p>
    <div class="grid sm:grid-cols-2 gap-3">
        @foreach ($sections as $key => $section)
            @php
                $complete = (bool) $section['complete'];
                $required = (bool) $section['required'];
                $statusLabel = $complete
                    ? __('borrower.profile.tab_complete')
                    : ($required ? __('borrower.profile.tab_incomplete') : __('borrower.profile.tab_optional'));
                $tagClass = $complete
                    ? 'bg-emerald-100 text-emerald-800 ring-emerald-200'
                    : ($required ? 'bg-amber-100 text-amber-900 ring-amber-200' : 'bg-gray-100 text-gray-600 ring-gray-200');
                $cardClass = $complete
                    ? 'ring-emerald-200/80 hover:ring-emerald-300 bg-gradient-to-br from-emerald-50/80 to-white'
                    : ($required ? 'ring-amber-200/80 hover:ring-amber-300 bg-gradient-to-br from-amber-50/40 to-white' : 'ring-gray-200/80 hover:ring-brand/30 bg-white');
            @endphp
            <a href="#section-{{ $key }}"
               class="group rounded-2xl ring-1 p-5 transition hover:shadow-md {{ $cardClass }}">
                <div class="flex items-start justify-between gap-3">
                    <span class="text-2xl leading-none" aria-hidden="true">{{ $section['icon'] }}</span>
                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1 {{ $tagClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <h3 class="font-semibold text-gray-900 mt-3 group-hover:text-brand transition">{{ $section['label'] }}</h3>
                <p class="text-xs text-gray-500 mt-1">{{ $section['hint'] }}</p>
            </a>
        @endforeach
    </div>
</section>
