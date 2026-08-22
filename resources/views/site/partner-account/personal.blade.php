@props([
    'partner',
    'portal',
    'profileRoute',
    'updateRoute',
    'layoutComponent',
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'accountTabs' => [],
    'canChangeCode' => false,
])

@php
    $isAffiliate = $partner instanceof \App\Models\Partner && $partner->isAffiliate();
    $isLender = $partner instanceof \App\Models\Lender;
    $isCompany = $partner instanceof \App\Models\Partner && $partner->isCompanyApplicant();
    $meta = $partner->metadata ?? [];
    $identity = is_array($meta['identity'] ?? null) ? $meta['identity'] : [];
    $noPhysicalCard = (bool) old('no_physical_nida_card', $identity['no_physical_nida_card'] ?? false);
    $hasNida = filled($identity['national_id'] ?? null);
    $nidaLocked = $hasNida;
    $personaName = $isCompany
        ? ($partner->contactPersonName() ?: '')
        : (string) ($partner->name ?? '');
    $hasContact = $isCompany
        ? (filled($personaName) && filled($partner->phone) && filled($partner->email))
        : (filled($partner->name) && filled($partner->phone));
    $identityComplete = $hasNida && ($noPhysicalCard || (filled($identity['national_id_front'] ?? null) && filled($identity['national_id_back'] ?? null)));
    $nameLabel = $isCompany
        ? __('site.partner_account.contact_person_name')
        : __('site.partner_account.display_name');
@endphp

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile">

    <x-site.borrower-page-header :eyebrow="$eyebrow" :title="$title" :subtitle="$subtitle" share="kf-psec-personal" />

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'personal',
        'profileRoute' => $profileRoute,
    ])

    <div class="space-y-4">
        {{-- Contact details --}}
        <x-site.profile-section-card
            section-id="section-contact"
            icon="👤"
            :title="__('site.partner_account.contact_details')"
            :complete="$hasContact"
            :collapsible="true">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ $nameLabel }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $personaName ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.phone') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $partner->phone ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.email') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $partner->email ?: '—' }}</dd>
                    </div>
                </dl>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route($updateRoute, ['section' => 'personal']) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="focus" value="contact">
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">{{ $nameLabel }}</label>
                        <input name="name" value="{{ old('name', $personaName) }}" required
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand"
                               placeholder="{{ $isCompany ? __('site.partner_account.contact_person_placeholder') : '' }}">
                        @if ($isCompany)
                            <p class="text-xs text-gray-500 mt-1">{{ __('site.partner_account.contact_person_hint') }}</p>
                        @endif
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <x-site.phone-input name="phone" :label="__('site.partner_account.phone')" :value="old('phone', $partner->phone)" variant="rounded" />
                        <div>
                            <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.email') }}</label>
                            <input name="email" type="email" value="{{ old('email', $partner->email) }}" @if($isCompany) required @endif
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand">
                        </div>
                    </div>
                    <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        {{-- Identity / NIDA --}}
        <x-site.profile-section-card
            section-id="section-identity"
            icon="🪪"
            :title="__('site.partner_account.nida_number')"
            :complete="$identityComplete"
            :collapsible="true">
            <x-slot:view>
                @if ($hasNida)
                    <p class="text-lg font-mono font-semibold text-gray-900">{{ $identity['national_id'] }}</p>
                    @if ($noPhysicalCard)
                        <div class="mt-3 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-3">
                            <p class="text-sm font-semibold text-amber-900">{{ __('site.partner_account.no_physical_card') }}</p>
                        </div>
                    @endif
                    <div class="grid sm:grid-cols-2 gap-3 mt-4" x-data="{ expandedUrl: null }">
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                            <p class="text-xs text-gray-500">{{ __('site.partner_account.nida_front') }}</p>
                            @if (filled($identity['national_id_front'] ?? null))
                                @php $frontUrl = asset('storage/'.$identity['national_id_front']); @endphp
                                <button type="button" @click="expandedUrl = @js($frontUrl)"
                                        class="mt-2 h-20 w-full max-w-[7rem] object-cover rounded-lg ring-1 ring-gray-200 overflow-hidden cursor-zoom-in block">
                                    <img src="{{ $frontUrl }}" alt="" class="h-full w-full object-cover">
                                </button>
                            @else
                                <p class="text-amber-700 font-semibold text-sm mt-2">{{ __('site.partner_account.missing') }}</p>
                            @endif
                        </div>
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                            <p class="text-xs text-gray-500">{{ __('site.partner_account.nida_back') }}</p>
                            @if (filled($identity['national_id_back'] ?? null))
                                @php $backUrl = asset('storage/'.$identity['national_id_back']); @endphp
                                <button type="button" @click="expandedUrl = @js($backUrl)"
                                        class="mt-2 h-20 w-full max-w-[7rem] object-cover rounded-lg ring-1 ring-gray-200 overflow-hidden cursor-zoom-in block">
                                    <img src="{{ $backUrl }}" alt="" class="h-full w-full object-cover">
                                </button>
                            @else
                                <p class="text-amber-700 font-semibold text-sm mt-2">{{ __('site.partner_account.missing') }}</p>
                            @endif
                        </div>
                        <div x-show="expandedUrl" x-cloak class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
                             @click.self="expandedUrl = null" @keydown.escape.window="expandedUrl = null">
                            <img :src="expandedUrl" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl">
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-500">{{ __('site.partner_account.section_empty') }}</p>
                @endif
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route($updateRoute, ['section' => 'personal']) }}" enctype="multipart/form-data" class="space-y-4"
                      x-data="{ noCard: @js($noPhysicalCard) }">
                    @csrf @method('PUT')
                    <input type="hidden" name="focus" value="identity">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('site.partner_account.nida_number') }}</label>
                        @if ($nidaLocked)
                            <input type="text" name="national_id" value="{{ old('national_id', $identity['national_id'] ?? '') }}"
                                   readonly
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono uppercase bg-gray-50 text-gray-500">
                        @else
                            <x-site.national-id-input name="national_id" :value="old('national_id', $identity['national_id'] ?? '')" required />
                        @endif
                    </div>
                    <label class="flex items-start gap-3 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3 cursor-pointer">
                        <input type="checkbox" name="no_physical_nida_card" value="1" x-model="noCard"
                               class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand" @checked($noPhysicalCard)>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">{{ __('site.partner_account.no_physical_card') }}</span>
                            <span class="block text-xs text-gray-500 mt-0.5">{{ __('site.partner_account.no_physical_card_hint') }}</span>
                        </span>
                    </label>
                    <div x-show="!noCard" x-cloak class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.nida_front') }}</label>
                            <input type="file" name="national_id_front" accept=".jpg,.jpeg,.png,.pdf"
                                   @if(! filled($identity['national_id_front'] ?? null)) required @endif
                                   class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold">
                            @error('national_id_front')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.nida_back') }}</label>
                            <input type="file" name="national_id_back" accept=".jpg,.jpeg,.png,.pdf"
                                   @if(! filled($identity['national_id_back'] ?? null)) required @endif
                                   class="w-full text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-muted file:text-brand file:font-semibold">
                            @error('national_id_back')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" :allow-empty="$identityComplete" />
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        @if ($isAffiliate)
            {{-- Promo code --}}
            <x-site.profile-section-card
                section-id="section-promo"
                icon="🏷️"
                :title="__('site.affiliate_portal.personalize_code')"
                :complete="filled($partner->affiliate_code)"
                :collapsible="true">
                <x-slot:view>
                    <p class="text-sm font-mono font-bold text-brand">{{ $partner->affiliate_code ?: '—' }}</p>
                    <p class="text-xs text-gray-500 mt-2">
                        {{ $canChangeCode ? __('site.affiliate_portal.code_change_hint') : __('site.affiliate_portal.code_locked_hint') }}
                    </p>
                    @if ($partner->affiliate_code)
                        <a href="{{ route('site.affiliate.verify', $partner->affiliate_code) }}" target="_blank" class="inline-flex mt-3 text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.verify_link') }} →</a>
                    @endif
                </x-slot:view>
                <x-slot:form>
                    <form method="POST" action="{{ route($updateRoute, ['section' => 'personal']) }}" class="space-y-4">
                        @csrf @method('PUT')
                        <input type="hidden" name="focus" value="promo">
                        <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.code_rules') }}</p>
                        <p class="text-xs text-amber-700">{{ $canChangeCode ? __('site.affiliate_portal.code_change_hint') : __('site.affiliate_portal.code_locked_hint') }}</p>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.promo_code') }}</label>
                            <input name="affiliate_code" value="{{ old('affiliate_code', $partner->affiliate_code) }}"
                                   pattern="[A-Za-z0-9_-]{3,24}" maxlength="24"
                                   @disabled(! $canChangeCode)
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono uppercase focus:border-brand focus:ring-brand/10 outline-none disabled:bg-gray-50 disabled:text-gray-500">
                            @error('affiliate_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        @if ($canChangeCode)
                            <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
                        @endif
                    </form>
                </x-slot:form>
            </x-site.profile-section-card>
        @endif

        @if ($isLender)
            {{-- Investment preferences --}}
            <x-site.profile-section-card
                section-id="section-preferences"
                icon="⚖️"
                :title="__('site.partner_account.preferences_section')"
                :complete="true"
                :collapsible="true">
                <x-slot:view>
                    <p class="text-sm font-semibold text-gray-900 capitalize">{{ __('site.partner_account.risk_preference') }}: {{ $partner->risk_preference }}</p>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('site.partner_account.auto_invest') }}: {{ $partner->auto_invest ? __('site.partner_account.yes') : __('site.partner_account.no') }}
                    </p>
                </x-slot:view>
                <x-slot:form>
                    <form method="POST" action="{{ route($updateRoute, ['section' => 'personal']) }}" class="space-y-4">
                        @csrf @method('PUT')
                        <input type="hidden" name="focus" value="preferences">
                        <div>
                            <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.risk_preference') }}</label>
                            <div class="grid grid-cols-3 gap-2 mt-1">
                                @foreach (['low' => __('site.partner_account.risk_low'), 'medium' => __('site.partner_account.risk_medium'), 'high' => __('site.partner_account.risk_high')] as $val => $label)
                                    <label @class([
                                        'cursor-pointer rounded-xl border-2 p-3 text-center transition',
                                        'border-brand bg-brand-muted/40' => old('risk_preference', $partner->risk_preference) === $val,
                                        'border-gray-200 hover:bg-brand-muted/20' => old('risk_preference', $partner->risk_preference) !== $val,
                                    ])>
                                        <input type="radio" name="risk_preference" value="{{ $val }}" @checked(old('risk_preference', $partner->risk_preference) === $val) class="sr-only">
                                        <p class="font-semibold text-sm text-gray-900">{{ $label }}</p>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="auto_invest" value="1" @checked(old('auto_invest', $partner->auto_invest)) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                            <span class="text-sm text-gray-700">{{ __('site.partner_account.auto_invest_hint') }}</span>
                        </label>
                        <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
                    </form>
                </x-slot:form>
            </x-site.profile-section-card>
        @endif
    </div>

</x-dynamic-component>
