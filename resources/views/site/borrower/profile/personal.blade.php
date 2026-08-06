<x-site.borrower-layout :title="brand_title(__('borrower.profile.account_title'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.personal'),
            'subtitle' => __('borrower.profile.personal_sections_hint'),
            'customer' => $customer,
            'active' => 'personal',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'nida',
        ])

        @php
            $personalGaps = app(\App\Services\ProfileValidationService::class)->personalGaps($customer);
        @endphp
        @if ($personalGaps !== [])
            <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <span class="font-semibold text-amber-800">{{ __('borrower.profile.gaps.banner_compact') }}</span>
                @foreach ($personalGaps as $gap)
                    <a href="{{ $gap['url'] }}" class="font-semibold text-brand hover:underline">{{ $gap['label'] }}</a>
                    @if (! $loop->last)<span class="text-gray-300">·</span>@endif
                @endforeach
            </div>
        @endif

        @php
            $locked = (bool) $customer->identity_locked;
            $nidaSaved = filled($customer->national_id);
            $nidaReadonly = $locked || $nidaSaved;
            $editing = ($wizardMode ?? false) || ($editing ?? false);
            $requireIdentityDuringProfile = app(\App\Services\ProfileCompletionService::class)->identityRequiredDuringProfile();
            $nidaDocs = $nidaDocuments ?? collect();
            $nidaFront = $nidaDocs->get('national_id_front');
            $nidaBack = $nidaDocs->get('national_id_back');
            $altDocs = $nidaDocs;
            $uploadsComplete = app(\App\Services\ProfileValidationService::class)->nationalIdUploadsComplete($customer);
            $idPhotosLocked = $locked || (
                $uploadsComplete
                && ! app(\App\Services\ProfileRevisionService::class)->hasOpenRevision($customer, 'nida_docs')
                && ! app(\App\Services\ProfileRevisionService::class)->hasOpenRevision($customer, 'nida')
            );
            $noPhysicalCard = (bool) old('no_physical_nida_card', $customer->no_physical_nida_card);
            $hasIdentity = $nidaSaved && (
                ! $requireIdentityDuringProfile || $uploadsComplete
            );
            $readonly = 'kf-field-readonly';
            $editable = 'kf-field';
            $hasContact = filled($customer->phone) || filled($customer->email);
            $kinComplete = app(\App\Services\ProfileValidationService::class)->isKinComplete($customer);
            $kinStale = in_array('kin', app(\App\Services\KycFreshnessService::class)->sectionsDueForRefresh($customer), true);
            $kinName = $customer->nok_name ?: trim(($customer->nok_first_name ?? '').' '.($customer->nok_last_name ?? ''));
            $faceKey = $customer->face_verification_status ?? 'incomplete';
            $faceComplete = in_array($faceKey, ['verified', 'pending'], true);
            $faceHasPhotos = ($facePhotos ?? collect())->isNotEmpty();
            $focusHash = request()->query('focus');
            $errorFocus = match (true) {
                $errors->hasAny(['national_id_front', 'national_id_back', 'alternate_id_types', 'alternate_id_front', 'alternate_id_back', 'no_physical_nida_card', 'passport', 'voter_id', 'driving_license', 'other_id']) => 'id_images',
                $errors->hasAny(['national_id']) => 'identity',
                $errors->hasAny(['phone', 'email']) => 'contact',
                $errors->hasAny(['nok_first_name', 'nok_last_name', 'nok_name', 'nok_phone', 'nok_relationship', 'nok_region', 'nok_district', 'nok_street']) => 'kin',
                $errors->hasAny(['signature_data', 'signer_name']) => 'signature',
                default => null,
            };
            $focusHash = $focusHash ?: $errorFocus;
            $editFocus = $errorFocus; // validation errors open the form; deep links expand view only
        @endphp

        @include('site.borrower.profile._nida_result', ['customer' => $customer])

        @if ($customer->nida_locked_until && now()->lt($customer->nida_locked_until))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900">
                <p class="font-semibold">{{ __('borrower.nida.verification_locked_banner', ['time' => $customer->nida_locked_until->timezone(config('app.timezone'))->format('d M Y H:i')]) }}</p>
                <p class="mt-1">{{ __('borrower.nida.verification_locked_appeal') }}</p>
                <a href="{{ route('site.borrower.support') }}" class="inline-flex mt-2 text-xs font-semibold text-red-800 underline">{{ __('borrower.nida.verification_locked_support') }}</a>
            </div>
        @endif

        @if (($wizardMode ?? false) && ($wizardKey ?? 'nida') === 'kin')
            <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal', 'wizard' => 1]) }}{{ ! empty($returnUrl) ? '&return='.urlencode($returnUrl) : '' }}" class="glass-card p-6 space-y-8">
                @csrf @method('PUT')
                <input type="hidden" name="wizard" value="1">
                <input type="hidden" name="focus" value="kin">
                @if (! empty($returnUrl))
                    <input type="hidden" name="return" value="{{ $returnUrl }}">
                @endif
                <div id="next-of-kin" class="scroll-mt-24">
                    <h3 class="font-semibold mb-1 flex items-center gap-2"><span aria-hidden="true">👨‍👩‍👧</span> {{ __('borrower.profile.kin_info') }}</h3>
                    <div class="space-y-4 mt-4">
                        <x-site.kin-fields :customer="$customer" :input-class="$editable" />
                        <x-site.address-fields prefix="nok" :region="old('nok_region', $customer->nok_region)" :district="old('nok_district', $customer->nok_district)" :ward="old('nok_ward', $customer->nok_ward)" :street="old('nok_street', $customer->nok_street)" />
                    </div>
                </div>
                <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.profile_wizard.save_continue') }}
                </button>
            </form>
        @else
            <div class="space-y-4">
                {{-- Identity / NIDA --}}
                <x-site.profile-section-card
                    section-id="profile-identity"
                    icon="🪪"
                    :title="__('borrower.profile.fields.national_id')"
                    :complete="$hasIdentity"
                    :empty="! $hasIdentity"
                    :default-open="$focusHash === 'identity'"
                    :default-edit="$editFocus === 'identity'">
                    <x-slot:view>
                        @if ($nidaSaved)
                            <div>
                                <p class="text-lg font-mono font-semibold text-gray-900">{{ $customer->national_id }}</p>
                                <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                    <span aria-hidden="true">🔒</span>{{ $locked ? __('borrower.nida.locked_title') : __('borrower.nida.saved_locked_title') }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">{{ $locked ? __('borrower.nida.locked_hint') : __('borrower.nida.saved_locked_hint') }}</p>
                            </div>
                        @else
                            <p class="text-sm text-gray-500">{{ __('borrower.profile.section_empty') }}</p>
                            <button type="button" @click="openEdit()" class="mt-2 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                        @endif
                        @unless ($requireIdentityDuringProfile)
                            <p class="text-xs text-gray-400 mt-2">{{ __('borrower.profile.identity_deferred_body') }}</p>
                        @endunless
                    </x-slot:view>
                    <x-slot:form>
                        @if ($nidaReadonly)
                            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900 mb-4">
                                <p class="font-semibold">{{ $locked ? __('borrower.nida.locked_title') : __('borrower.nida.saved_locked_title') }}</p>
                                <p class="mt-1 text-emerald-800">{{ $locked ? __('borrower.nida.locked_hint') : __('borrower.nida.saved_locked_hint') }}</p>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="identity">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-2">{{ __('borrower.nida.number') }} <span class="text-red-500">*</span></label>
                                    <x-site.nida-input name="national_id" :value="old('national_id', $customer->national_id)" :required="! $nidaReadonly" :readonly="$nidaReadonly" />
                                    @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <x-site.gated-submit class="mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm" :label="__('borrower.profile.save')" :allow-empty="$nidaSaved" />
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- ID images: NIDA card photos or alternate ID --}}
                <x-site.profile-section-card
                    section-id="profile-id-images"
                    icon="🖼️"
                    :title="__('borrower.profile.id_images_title')"
                    :complete="$uploadsComplete"
                    :empty="! $uploadsComplete"
                    :default-open="$focusHash === 'id_images'"
                    :default-edit="$editFocus === 'id_images'">
                    <x-slot:view>
                        <div x-data="{ expandedUrl: null }" class="space-y-3">
                            @if ($customer->no_physical_nida_card)
                                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-3">
                                    <p class="text-sm font-semibold text-amber-900">{{ __('borrower.nida.no_card_saved_title') }}</p>
                                    <p class="text-xs text-amber-800 mt-1">{{ __('borrower.nida.no_card_saved_hint') }}</p>
                                </div>
                                @php
                                    $altPreview = collect(['passport', 'voter_id', 'driving_license', 'other_id'])
                                        ->map(fn ($code) => $altDocs->get($code))
                                        ->filter();
                                @endphp
                                @forelse ($altPreview as $doc)
                                    @if ($doc?->file_path)
                                        @php $url = asset('storage/'.$doc->file_path); @endphp
                                        <button type="button" @click="expandedUrl = @js($url)"
                                                class="h-28 w-28 rounded-xl overflow-hidden ring-1 ring-gray-200 bg-white cursor-zoom-in block">
                                            <img src="{{ $url }}" alt="" class="h-full w-full object-cover">
                                        </button>
                                    @endif
                                @empty
                                    <p class="text-sm text-gray-500">{{ __('borrower.profile.id_images_empty') }}</p>
                                    <button type="button" @click="openEdit()" class="mt-2 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                                @endforelse
                            @else
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                                        <p class="text-xs text-gray-500">{{ __('borrower.profile.nida_front') }}</p>
                                        <div class="mt-2">
                                            @if ($nidaFront?->file_path)
                                                @php $frontUrl = asset('storage/'.$nidaFront->file_path); @endphp
                                                <button type="button" @click="expandedUrl = @js($frontUrl)"
                                                        class="h-28 w-full max-w-[8rem] rounded-lg ring-1 ring-gray-200 overflow-hidden bg-white cursor-zoom-in block"
                                                        title="{{ __('borrower.profile.view_document') }}">
                                                    <img src="{{ $frontUrl }}" alt="" class="h-full w-full object-cover object-center">
                                                </button>
                                                <p class="text-[11px] text-gray-500 mt-1.5">{{ __('borrower.profile.tap_to_enlarge') }}</p>
                                            @else
                                                <span class="font-semibold text-amber-700 text-sm">{{ __('borrower.profile.missing') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                                        <p class="text-xs text-gray-500">{{ __('borrower.profile.nida_back') }}</p>
                                        <div class="mt-2">
                                            @if ($nidaBack?->file_path)
                                                @php $backUrl = asset('storage/'.$nidaBack->file_path); @endphp
                                                <button type="button" @click="expandedUrl = @js($backUrl)"
                                                        class="h-28 w-full max-w-[8rem] rounded-lg ring-1 ring-gray-200 overflow-hidden bg-white cursor-zoom-in block"
                                                        title="{{ __('borrower.profile.view_document') }}">
                                                    <img src="{{ $backUrl }}" alt="" class="h-full w-full object-cover object-center">
                                                </button>
                                                <p class="text-[11px] text-gray-500 mt-1.5">{{ __('borrower.profile.tap_to_enlarge') }}</p>
                                            @else
                                                <span class="font-semibold text-amber-700 text-sm">{{ __('borrower.profile.missing') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @unless ($uploadsComplete)
                                    <button type="button" @click="openEdit()" class="mt-1 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                                @endunless
                            @endif
                            <div x-show="expandedUrl" x-cloak x-transition
                                 class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
                                 @keydown.escape.window="expandedUrl = null"
                                 @click.self="expandedUrl = null">
                                <button type="button" class="absolute top-4 right-4 text-white/90 text-sm font-semibold" @click="expandedUrl = null">{{ __('borrower.profile.cancel') }}</button>
                                <img :src="expandedUrl" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
                            </div>
                        </div>
                    </x-slot:view>
                    <x-slot:form>
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="id_images">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            @if ($nidaSaved)
                                <input type="hidden" name="national_id" value="{{ $customer->national_id }}">
                            @endif
                            <div class="space-y-4" x-data="{
                                noCard: @js($noPhysicalCard),
                                altTypes: @js(array_values(old('alternate_id_types', $customer->alternate_id_types ?? []))),
                            }">
                                @if ($idPhotosLocked)
                                    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-3 text-sm text-slate-700">
                                        {{ __('borrower.profile.id_photos_locked_hint') }}
                                    </div>
                                @endif
                                @unless ($locked || $idPhotosLocked)
                                    <label class="flex items-start gap-3 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3 cursor-pointer">
                                        <input type="checkbox" name="no_physical_nida_card" value="1" x-model="noCard"
                                               class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                               @checked($noPhysicalCard)>
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-900">{{ __('borrower.nida.no_card_label') }}</span>
                                            <span class="block text-xs text-gray-500 mt-0.5">{{ __('borrower.nida.no_card_hint') }}</span>
                                        </span>
                                    </label>
                                @endunless
                                <div x-show="!noCard" x-cloak class="space-y-4">
                                    <x-site.profile-document-field :document="$nidaFront" field-name="national_id_front" mode="single" :label="__('borrower.profile.nida_front')" input-host-id="nida-front-upload" :required="! $nidaFront && ! $noPhysicalCard && ! $idPhotosLocked" :read-only="$idPhotosLocked" />
                                    @error('national_id_front')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                    <x-site.profile-document-field :document="$nidaBack" field-name="national_id_back" mode="single" :label="__('borrower.profile.nida_back')" input-host-id="nida-back-upload" :required="! $nidaBack && ! $noPhysicalCard && ! $idPhotosLocked" :read-only="$idPhotosLocked" />
                                    @error('national_id_back')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div x-show="noCard" x-cloak class="space-y-4 rounded-xl bg-amber-50/80 ring-1 ring-amber-200 p-4">
                                    <div>
                                        <p class="text-sm font-semibold text-amber-950">{{ __('borrower.nida.alt_id_title') }}</p>
                                        <p class="text-xs text-amber-900/80 mt-1">{{ __('borrower.nida.alt_id_hint') }}</p>
                                    </div>
                                    @unless ($idPhotosLocked)
                                    <div class="grid sm:grid-cols-2 gap-2">
                                        @foreach ([
                                            'passport' => __('borrower.nida.alt_passport'),
                                            'voter_id' => __('borrower.nida.alt_voter'),
                                            'driving_license' => __('borrower.nida.alt_driving'),
                                            'other_id' => __('borrower.nida.alt_other'),
                                        ] as $type => $label)
                                            <label class="flex items-center gap-2 rounded-lg bg-white ring-1 ring-amber-100 px-3 py-2 text-sm cursor-pointer">
                                                <input type="checkbox" name="alternate_id_types[]" value="{{ $type }}"
                                                       class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                                       x-model="altTypes">
                                                <span class="text-gray-900">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('alternate_id_types')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900 mb-1.5">{{ __('borrower.nida.alt_notes_label') }}</label>
                                        <input type="text" name="alternate_id_notes" value="{{ old('alternate_id_notes', $customer->alternate_id_notes) }}"
                                               class="kf-field" placeholder="{{ __('borrower.nida.alt_notes_placeholder') }}">
                                    </div>
                                    @endunless
                                    <div class="space-y-3" x-show="altTypes.includes('passport')">
                                        <x-site.profile-document-field :document="$altDocs->get('passport')" field-name="passport" mode="single" :label="__('borrower.nida.alt_passport')" input-host-id="passport-upload" :read-only="$idPhotosLocked" />
                                    </div>
                                    <div class="space-y-3" x-show="altTypes.includes('voter_id')">
                                        <x-site.profile-document-field :document="$altDocs->get('voter_id')" field-name="voter_id" mode="single" :label="__('borrower.nida.alt_voter')" input-host-id="voter-upload" :read-only="$idPhotosLocked" />
                                    </div>
                                    <div class="space-y-3" x-show="altTypes.includes('driving_license')">
                                        <x-site.profile-document-field :document="$altDocs->get('driving_license')" field-name="driving_license" mode="single" :label="__('borrower.nida.alt_driving')" input-host-id="license-upload" :read-only="$idPhotosLocked" />
                                    </div>
                                    <div class="space-y-3" x-show="altTypes.includes('other_id')">
                                        <x-site.profile-document-field :document="$altDocs->get('other_id')" field-name="other_id" mode="single" :label="__('borrower.nida.alt_other')" input-host-id="other-id-upload" :read-only="$idPhotosLocked" />
                                    </div>
                                </div>
                            </div>
                            @unless ($idPhotosLocked)
                            <x-site.gated-submit class="mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm" :label="__('borrower.profile.save')" :allow-empty="$uploadsComplete" />
                            @endunless
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Contact --}}
                <x-site.profile-section-card
                    section-id="profile-contact"
                    icon="📱"
                    :title="__('borrower.profile.contact_details')"
                    :complete="$hasContact"
                    :empty="! $hasContact"
                    :default-open="$focusHash === 'contact'"
                    :default-edit="$editFocus === 'contact'">
                    <x-slot:view>
                        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500">{{ __('borrower.profile.fields.phone') }}</dt>
                                @if ($customer->phone)
                                    <dd class="font-medium mt-0.5">{{ $customer->phone }}</dd>
                                @else
                                    <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                                @endif
                            </div>
                            @if (filled($customer->email) && ! str_ends_with(strtolower($customer->email), '@phone.kopafasta.local'))
                                <div><dt class="text-gray-500">{{ __('borrower.profile.fields.email') }}</dt><dd class="font-medium mt-0.5">{{ $customer->email }}</dd></div>
                            @endif
                        </dl>
                    </x-slot:view>
                    <x-slot:form>
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="contact">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <div class="grid sm:grid-cols-2 gap-4">
                                <x-site.phone-input
                                    name="phone"
                                    :label="__('borrower.profile.fields.phone')"
                                    :value="old('phone', $customer->phone)"
                                    :locked-country="$customer->country_code ?? 'TZ'"
                                    :input-class="$editable"
                                />
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }}</label>
                                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="{{ $editable }}">
                                </div>
                            </div>
                            <x-site.gated-submit class="mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm" :label="__('borrower.profile.save')" />
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Next of kin --}}
                <x-site.profile-section-card
                    section-id="profile-kin"
                    icon="👨‍👩‍👧"
                    :title="__('borrower.profile.kin_info')"
                    :complete="$kinComplete"
                    :stale="$kinStale"
                    :empty="! $kinComplete"
                    :default-open="$focusHash === 'kin'"
                    :default-edit="$editFocus === 'kin'">
                    <x-slot:view>
                        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500">{{ __('borrower.profile.fields.full_name') }}</dt>
                                @if ($kinName)
                                    <dd class="font-medium mt-0.5">{{ $kinName }}</dd>
                                @else
                                    <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                                @endif
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('borrower.profile.fields.relationship') }}</dt>
                                @if ($customer->nok_relationship)
                                    <dd class="font-medium mt-0.5">{{ kin_relationship_label($customer->nok_relationship) }}</dd>
                                @else
                                    <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                                @endif
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('borrower.profile.fields.phone') }}</dt>
                                @if ($customer->nok_phone)
                                    <dd class="font-medium mt-0.5">{{ $customer->nok_phone }}</dd>
                                @else
                                    <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                                @endif
                            </div>
                            @if (filled($customer->nok_region))
                                <div><dt class="text-gray-500">{{ __('borrower.profile.region') }}</dt><dd class="font-medium mt-0.5">{{ $customer->nok_region }}</dd></div>
                            @endif
                        </dl>
                    </x-slot:view>
                    <x-slot:form>
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="kin">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <div class="space-y-4">
                                <x-site.kin-fields :customer="$customer" :input-class="$editable" />
                                <x-site.address-fields prefix="nok" :region="old('nok_region', $customer->nok_region)" :district="old('nok_district', $customer->nok_district)" :ward="old('nok_ward', $customer->nok_ward)" :street="old('nok_street', $customer->nok_street)" />
                            </div>
                            <x-site.gated-submit class="mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm" :label="__('borrower.profile.save')" />
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Face photos — always available on personal profile --}}
                <x-site.profile-section-card
                    section-id="profile-face"
                    icon="📷"
                    :title="__('borrower.nida.face_title')"
                    :complete="$faceComplete"
                    :empty="! $faceHasPhotos && ! $faceComplete"
                    :inline-edit="true"
                    :default-open="$focusHash === 'face'"
                    :default-edit="$focusHash === 'face' && in_array($faceKey, ['rejected', 'revision_required', 'incomplete'], true)"
                    :allow-overflow="true">
                    <x-slot:view>
                        @if (! empty($faceAngles ?? []))
                            <x-site.face-verification-status
                                :customer="$customer"
                                :photos="$facePhotos ?? collect()"
                                :angles="$faceAngles"
                                :compact="true"
                            />
                        @else
                            @php
                                $faceStatus = match ($faceKey) {
                                    'verified' => [__('borrower.nida.face_status.verified'), 'text-emerald-700'],
                                    'pending'  => [__('borrower.nida.face_status.submitted'), 'text-sky-700'],
                                    'rejected' => [__('borrower.nida.face_status.failed'), 'text-red-700'],
                                    'revision_required' => [__('borrower.nida.face_status.revision_required'), 'text-amber-700'],
                                    default    => [__('borrower.nida.face_status.incomplete'), 'text-amber-700'],
                                };
                            @endphp
                            <p class="text-sm font-semibold {{ $faceStatus[1] }}">{{ $faceStatus[0] }}</p>
                            <button type="button" @click="openEdit()" class="inline-flex mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">
                                {{ __('borrower.profile.add_details') }}
                            </button>
                        @endif
                    </x-slot:view>
                    <x-slot:form>
                        @if (in_array($faceKey, ['rejected', 'revision_required', 'incomplete'], true) && isset($faceSteps, $faceUploadUrls))
                            @if ($faceKey === 'revision_required')
                                <p class="text-sm text-amber-800 mb-4 font-medium">{{ __('borrower.apply.checklist.face_revision') }}</p>
                            @elseif ($faceKey === 'rejected' && filled($customer->face_rejection_notes))
                                <p class="text-sm text-red-800 mb-4">{{ $customer->face_rejection_notes }}</p>
                            @endif
                            @include('site.borrower.profile._face_inline', [
                                'steps' => $faceSteps,
                                'uploadUrls' => $faceUploadUrls,
                                'deleteUrls' => $faceDeleteUrls ?? [],
                                'wizard' => $faceWizard ?? ['current_index' => 0],
                            ])
                        @elseif (isset($faceSteps, $faceUploadUrls) || ! empty($faceAngles ?? []))
                            <x-site.face-verification-status
                                :customer="$customer"
                                :photos="$facePhotos ?? collect()"
                                :angles="$faceAngles ?? []"
                                :compact="true"
                            />
                        @else
                            <p class="text-sm text-gray-600">{{ __('borrower.nida.face_capture_hint') }}</p>
                        @endif
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Legal signature (reusable across contracts) --}}
                @php
                    $signatureService = app(\App\Services\BorrowerSignatureService::class);
                    $hasLegalSignature = $signatureService->hasProfileSignature($customer);
                @endphp
                <x-site.profile-section-card
                    section-id="profile-signature"
                    icon="✍️"
                    :title="__('borrower.profile.legal_signature')"
                    :complete="$hasLegalSignature"
                    :empty="! $hasLegalSignature"
                    :inline-edit="true"
                    :default-open="$focusHash === 'signature'"
                    :default-edit="$editFocus === 'signature'">
                    <x-slot:view>
                        @if ($hasLegalSignature)
                            <p class="text-sm font-semibold text-gray-900">{{ $customer->legal_signer_name ?: $customer->full_name }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.profile.legal_signature_saved_at', ['date' => optional($customer->legal_signed_at)->format('d M Y') ?? '—']) }}</p>
                            <img src="{{ $customer->legal_signature_data }}" alt="" class="mt-3 max-h-28 border border-gray-200 rounded-xl bg-white">
                            <p class="text-xs text-gray-500 mt-3">{{ __('borrower.profile.legal_signature_notice') }}</p>
                        @else
                            <p class="text-sm text-gray-600">{{ __('borrower.profile.legal_signature_empty') }}</p>
                            <p class="text-xs text-gray-500 mt-2">{{ __('borrower.profile.legal_signature_notice') }}</p>
                        @endif
                    </x-slot:view>
                    <x-slot:form>
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" class="space-y-4"
                              x-data
                              @submit="
                                  const pad = $el.querySelector('[data-signature-pad]');
                                  const alpine = pad && window.Alpine ? Alpine.$data(pad) : null;
                                  if (alpine) {
                                      const hidden = $el.querySelector('[name=signature_data]');
                                      if (hidden) hidden.value = alpine.dataUrl || '';
                                  }
                              ">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="signature">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <p class="text-sm text-gray-600">{{ __('borrower.profile.legal_signature_notice') }}</p>
                            <x-site.signature-pad
                                :default-name="$customer->full_name"
                                :readonly-name="true"
                                :verified="filled($customer->nida_verified_at)"
                                :include-in-form="true"
                                :initial-data-url="$customer->legal_signature_data ?? ''"
                            />
                            <x-site.gated-submit class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm" :label="__('borrower.profile.save')" />
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>
            </div>
        @endif

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'nida'])
    </div>
</x-site.borrower-layout>
