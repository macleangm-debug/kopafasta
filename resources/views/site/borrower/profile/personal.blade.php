<x-site.borrower-layout :title="brand_title(__('borrower.profile.personal'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.personal'),
            'subtitle' => null,
            'customer' => $customer,
            'active' => 'personal',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'nida',
        ])

        @php
            $personalGaps = app(\App\Services\ProfileValidationService::class)->personalGaps($customer);
        @endphp
        @if ($personalGaps !== [] && ! ($solo ?? false))
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
            $nidaReadonly = $locked;
            $editing = ($wizardMode ?? false) || ($editing ?? false);
            $requireIdentityDuringProfile = app(\App\Services\ProfileCompletionService::class)->identityRequiredDuringProfile();
            $nidaDocs = $nidaDocuments ?? collect();
            $nidaFront = $nidaDocs->get('national_id_front');
            $nidaBack = $nidaDocs->get('national_id_back');
            $altDocs = $nidaDocs;
            $uploadsComplete = app(\App\Services\ProfileValidationService::class)->nationalIdUploadsComplete($customer);
            $idPhotosReviewHint = ! $locked && $uploadsComplete
                && ! app(\App\Services\ProfileRevisionService::class)->hasOpenRevision($customer, 'nida_docs')
                && ! app(\App\Services\ProfileRevisionService::class)->hasOpenRevision($customer, 'nida');
            $noPhysicalCard = (bool) old('no_physical_nida_card', $customer->no_physical_nida_card);
            $nidaRequired = app(\App\Services\IdentityVerificationPolicyService::class)->nidaRequired();
            // NIDA number card is complete when the number is persisted — images are a separate card.
            $readonly = 'kf-field-readonly';
            $editable = 'kf-field';
            $hasContact = filled($customer->phone) || filled($customer->email);
            $kinComplete = app(\App\Services\ProfileValidationService::class)->isKinComplete($customer);
            $familyComplete = app(\App\Services\ProfileValidationService::class)->isFamilyComplete($customer);
            $requireMarriageCert = app(\App\Services\ProfileValidationService::class)->requiresMarriageCertificate();
            $isMarried = app(\App\Services\ProfileValidationService::class)->isMarried($customer);
            $marriageCertificate = $marriageCertificate ?? null;
            $kinStale = in_array('kin', app(\App\Services\KycFreshnessService::class)->sectionsDueForRefresh($customer), true);
            $faceKey = $customer->face_verification_status ?? 'incomplete';
            $faceComplete = in_array($faceKey, ['verified', 'pending'], true);
            $faceHasPhotos = ($facePhotos ?? collect())->isNotEmpty();
            $focusHash = request()->query('focus');
            $errorFocus = match (true) {
                $errors->hasAny(['national_id_front', 'national_id_back', 'alternate_id_types', 'alternate_id_front', 'alternate_id_back', 'no_physical_nida_card', 'passport', 'voter_id', 'driving_license', 'other_id']) => 'id_images',
                $errors->hasAny(['national_id']) => 'identity',
                $errors->hasAny(['phone', 'email']) => 'contact',
                $errors->hasAny(['marital_status', 'spouse_first_name', 'spouse_middle_name', 'spouse_last_name', 'number_of_children', 'marriage_certificate']) => 'family',
                $errors->hasAny(['nok_first_name', 'nok_last_name', 'nok_name', 'nok_phone', 'nok_relationship', 'nok_region', 'nok_district', 'nok_street']) => 'kin',
                $errors->hasAny(['signature_data', 'signer_name']) => 'signature',
                default => null,
            };
            $focusHash = $focusHash ?: $errorFocus;
            $editFocus = $errorFocus; // validation errors open the form; deep links expand view only
            $solo = request()->boolean('solo') && ! ($wizardMode ?? false);
            $soloFocus = (string) ($focusHash ?: '');
            $showSoloCard = function (array $keys) use ($solo, $soloFocus): bool {
                return ! $solo || in_array($soloFocus, $keys, true);
            };
            // Physical NIDA: stay on the view holder (Front→Back). Form is only for no-card / alt IDs.
            $idImagesAltErrors = $errors->hasAny(['alternate_id_types', 'alternate_id_front', 'alternate_id_back', 'no_physical_nida_card', 'passport', 'voter_id', 'driving_license', 'other_id']);
            $idImagesDefaultEdit = ($editFocus === 'id_images' && ($noPhysicalCard || $idImagesAltErrors));
            $idImagesDefaultOpen = $focusHash === 'id_images' || ((! $uploadsComplete) && ! $noPhysicalCard && ($solo || $focusHash === 'id_images' || $editFocus === 'id_images'));
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
                    @class(['hidden' => ! $showSoloCard(['identity', 'nida'])])
                    section-id="profile-identity"
                    icon="🪪"
                    :title="__('borrower.profile.fields.national_id')"
                    :complete="$nidaSaved"
                    :empty="! $nidaSaved"
                    :edit-allowed="! $nidaSaved"
                    :default-open="$focusHash === 'identity'"
                    :default-edit="$editFocus === 'identity' && ! $nidaSaved">
                    <x-slot:view>
                        @if ($nidaSaved)
                            <div>
                                <p class="text-lg font-mono font-semibold text-gray-900">{{ $customer->national_id }}</p>
                                @if ($locked)
                                    <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600">
                                        <span aria-hidden="true">🔒</span>{{ __('borrower.nida.saved_locked_title') }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.nida.saved_locked_hint') }}</p>
                                @else
                                    <p class="text-xs text-gray-500 mt-2">{{ __('borrower.nida.confirm_lock_pending_hint') }}</p>
                                @endif
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
                        @if ($nidaReadonly || $nidaSaved)
                            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm text-gray-800 mb-4">
                                <p class="font-semibold">{{ __('borrower.nida.saved_locked_title') }}</p>
                                <p class="mt-1 text-gray-600">{{ __('borrower.nida.saved_locked_hint') }}</p>
                            </div>
                        @else
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
                              @submit.prevent="
                                  if (@js($locked)) { $el.submit(); return; }
                                  $el.dispatchEvent(new CustomEvent('sync-before-submit', { bubbles: true }));
                                  const idInput = $el.querySelector('[name=national_id]');
                                  const newId = (idInput?.value || '').trim();
                                  if (! newId) { $el.submit(); return; }
                                  const message = @js(__('borrower.nida.confirm_lock_message')).replace(':number', newId);
                                  window.confirmForm(null, {
                                      title: @js(__('borrower.nida.confirm_lock_title')),
                                      message,
                                      confirmLabel: @js(__('borrower.nida.confirm_lock_confirm')),
                                      cancelLabel: @js(__('borrower.nida.confirm_lock_back')),
                                      tone: 'warning',
                                      onConfirm: () => {
                                          let lockField = $el.querySelector('[name=lock_national_id]');
                                          if (! lockField) {
                                              lockField = document.createElement('input');
                                              lockField.type = 'hidden';
                                              lockField.name = 'lock_national_id';
                                              $el.appendChild(lockField);
                                          }
                                          lockField.value = '1';
                                          $el.submit();
                                      },
                                  });
                              ">
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
                        @endif
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- ID images: NIDA card photos or alternate ID --}}
                <x-site.profile-section-card
                    @class(['hidden' => ! $showSoloCard(['id_images'])])
                    section-id="profile-id-images"
                    icon="🖼️"
                    :title="__('borrower.profile.id_images_title')"
                    :complete="$uploadsComplete"
                    :empty="! $uploadsComplete"
                    :empty-opens-view="! $noPhysicalCard"
                    :edit-allowed="$noPhysicalCard"
                    :default-open="$idImagesDefaultOpen"
                    :default-edit="$idImagesDefaultEdit">
                    <x-slot:view>
                        <div class="space-y-3">
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
                                <div x-data="{ expandedUrl: null }" class="space-y-3">
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
                                        <button type="button" @click="$dispatch('profile-card-open-edit', 'profile-id-images')" class="mt-2 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                                    @endforelse
                                    <div x-show="expandedUrl" x-cloak x-transition
                                         class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
                                         @keydown.escape.window="expandedUrl = null"
                                         @click.self="expandedUrl = null">
                                        <button type="button" class="absolute top-4 right-4 text-white/90 text-sm font-semibold" @click="expandedUrl = null">{{ __('borrower.profile.cancel') }}</button>
                                        <img :src="expandedUrl" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
                                    </div>
                                </div>
                            @else
                                @include('site.borrower.profile._national_id_holder', [
                                    'nidaFront' => $nidaFront,
                                    'nidaBack' => $nidaBack,
                                    'nidaSaved' => $nidaSaved,
                                    'nationalId' => $customer->national_id,
                                    'returnUrl' => $returnUrl ?? null,
                                    'uploadsComplete' => $uploadsComplete,
                                ])
                            @endif
                        </div>
                    </x-slot:view>
                    <x-slot:form>
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data"
                              data-inline-document-progress data-saving-message="{{ __('borrower.profile.uploading_documents') }}">
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
                                @unless ($locked)
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
                                {{-- Physical NIDA: capture only via view + (never this Save form). --}}
                                <div x-show="!noCard" x-cloak class="space-y-3">
                                    <p class="text-sm text-gray-600">{{ __('borrower.profile.national_id_holder_hint') }}</p>
                                    <button type="button"
                                            @click="$dispatch('profile-section-close-edit'); window.dispatchEvent(new CustomEvent('nida-open-source'))"
                                            class="text-sm font-semibold text-brand hover:underline">
                                        {{ __('borrower.profile.national_id_next_front') }} →
                                    </button>
                                </div>
                                <div x-show="noCard" x-cloak class="space-y-4 rounded-xl bg-amber-50/80 ring-1 ring-amber-200 p-4">
                                    <div>
                                        <p class="text-sm font-semibold text-amber-950">{{ __('borrower.nida.alt_id_title') }}</p>
                                        <p class="text-xs text-amber-900/80 mt-1">{{ __('borrower.nida.alt_id_hint') }}</p>
                                    </div>
                                    @unless ($locked)
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
                                        <x-site.profile-document-field :document="$altDocs->get('passport')" field-name="passport" mode="multi" :label="__('borrower.nida.alt_passport')" input-host-id="passport-upload" />
                                    </div>
                                    <div class="space-y-3" x-show="altTypes.includes('voter_id')">
                                        <x-site.profile-document-field :document="$altDocs->get('voter_id')" field-name="voter_id" mode="multi" :label="__('borrower.nida.alt_voter')" input-host-id="voter-upload" />
                                    </div>
                                    <div class="space-y-3" x-show="altTypes.includes('driving_license')">
                                        <x-site.profile-document-field :document="$altDocs->get('driving_license')" field-name="driving_license" mode="multi" :label="__('borrower.nida.alt_driving')" input-host-id="license-upload" />
                                    </div>
                                    <div class="space-y-3" x-show="altTypes.includes('other_id')">
                                        <x-site.profile-document-field :document="$altDocs->get('other_id')" field-name="other_id" mode="multi" :label="__('borrower.nida.alt_other')" input-host-id="other-id-upload" />
                                    </div>
                                </div>
                            </div>
                            {{-- Hifadhi only for alternate-ID / no-physical-card path. Physical NIDA autosaves from +. --}}
                            <div x-show="noCard" x-cloak>
                                <x-site.gated-submit class="mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm" :label="__('borrower.profile.save')" :allow-empty="$uploadsComplete" />
                            </div>
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Contact --}}
                <x-site.profile-section-card
                    @class(['hidden' => ! $showSoloCard(['contact'])])
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
                                    <dd class="font-medium text-gray-900 mt-0.5">{{ $customer->phone }}</dd>
                                @else
                                    <dd class="mt-0.5"><button type="button" @click="open = true" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button></dd>
                                @endif
                            </div>
                            @php
                                $contactEmail = filled($customer->email) && ! str_ends_with(strtolower((string) $customer->email), '@phone.kopafasta.local')
                                    ? $customer->email
                                    : null;
                            @endphp
                            <div>
                                <dt class="text-gray-500">{{ __('borrower.profile.fields.email') }}</dt>
                                <dd class="font-medium text-gray-900 mt-0.5">{{ $contactEmail ?: '—' }}</dd>
                            </div>
                        </dl>
                    </x-slot:view>
                    <x-slot:form>
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
                              data-kf-autosave
                              data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                              data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                              data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                              data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}">
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
                                    @php
                                        $emailEdit = old('email', $customer->email);
                                        if (is_string($emailEdit) && str_ends_with(strtolower($emailEdit), '@phone.kopafasta.local')) {
                                            $emailEdit = '';
                                        }
                                    @endphp
                                    <input type="email" name="email" value="{{ $emailEdit }}" class="{{ $editable }}">
                                </div>
                            </div>
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Family / marital --}}
                <x-site.profile-section-card
                    @class(['hidden' => ! $showSoloCard(['family'])])
                    section-id="profile-family"
                    icon="💍"
                    :title="__('borrower.profile.family_info')"
                    :complete="$familyComplete"
                    :empty="! $familyComplete"
                    :default-open="$focusHash === 'family'"
                    :default-edit="$editFocus === 'family'">
                    <x-slot:view>
                        @php
                            $familyViewRows = [];
                            $familyViewRows[] = [
                                'field' => 'marital_status',
                                'label' => __('borrower.profile.fields.marital_status'),
                                'value' => filled($customer->marital_status)
                                    ? __('borrower.profile.marital_options.'.$customer->marital_status)
                                    : null,
                            ];
                            $familyViewRows[] = [
                                'field' => 'number_of_children',
                                'label' => __('borrower.profile.fields.number_of_children'),
                                'value' => $customer->number_of_children !== null
                                    ? (string) $customer->number_of_children
                                    : null,
                            ];
                            // Always expose spouse mirrors so Edit→View sync works without reload.
                            $familyViewRows[] = [
                                'field' => 'spouse_first_name',
                                'label' => __('borrower.profile.fields.spouse_first_name'),
                                'value' => $customer->spouse_first_name,
                                'spouse' => true,
                            ];
                            $familyViewRows[] = [
                                'field' => 'spouse_middle_name',
                                'label' => __('borrower.profile.fields.spouse_middle_name'),
                                'value' => $customer->spouse_middle_name,
                                'spouse' => true,
                            ];
                            $familyViewRows[] = [
                                'field' => 'spouse_last_name',
                                'label' => __('borrower.profile.fields.spouse_last_name'),
                                'value' => $customer->spouse_last_name,
                                'spouse' => true,
                            ];
                            if ($marriageCertificate?->file_path ?? false) {
                                $familyViewRows[] = [
                                    'label' => __('borrower.profile.marriage_certificate'),
                                    'value' => 'doc',
                                    'href' => asset('storage/'.$marriageCertificate->file_path),
                                ];
                            }
                            $hasAnyFamilyValue = collect($familyViewRows)->contains(fn ($row) => filled($row['value'] ?? null) || ! empty($row['href']));
                            $showSpouse = $isMarried
                                || filled($customer->spouse_first_name)
                                || filled($customer->spouse_middle_name)
                                || filled($customer->spouse_last_name);
                        @endphp
                        @if (! $hasAnyFamilyValue)
                            <p class="text-sm text-gray-600">{{ __('borrower.profile.section_empty') }}</p>
                            <button type="button" @click="openEdit()" class="mt-2 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                        @else
                            <dl class="grid sm:grid-cols-2 gap-4 text-sm" data-kf-family-view>
                                @foreach ($familyViewRows as $row)
                                    @if (! empty($row['href']))
                                        <div>
                                            <dt class="text-gray-500">{{ $row['label'] }}</dt>
                                            <dd class="mt-0.5"><a href="{{ $row['href'] }}" target="_blank" class="text-sm font-semibold text-brand hover:underline">{{ __('borrower.profile.view_document') }}</a></dd>
                                        </div>
                                    @elseif (! empty($row['spouse']))
                                        <div @class(['hidden' => ! $showSpouse]) data-kf-spouse-row>
                                            <dt class="text-gray-500">{{ $row['label'] }}</dt>
                                            <dd class="font-medium text-gray-900 mt-0.5" @if (! empty($row['field'])) data-kf-view-field="{{ $row['field'] }}" @endif>{{ filled($row['value']) ? $row['value'] : '—' }}</dd>
                                        </div>
                                    @else
                                        <div>
                                            <dt class="text-gray-500">{{ $row['label'] }}</dt>
                                            <dd class="font-medium text-gray-900 mt-0.5" @if (! empty($row['field'])) data-kf-view-field="{{ $row['field'] }}" @endif>{{ filled($row['value']) ? $row['value'] : '—' }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                                @if ($isMarried && $requireMarriageCert && ! ($marriageCertificate?->file_path ?? false))
                                    <div class="sm:col-span-2">
                                        <p class="text-sm font-semibold text-amber-700">{{ __('borrower.profile.marriage_certificate') }} — {{ __('borrower.profile.missing') }}</p>
                                    </div>
                                @endif
                            </dl>
                        @endif
                    </x-slot:view>
                    <x-slot:form>
                        {{-- Same path as Activity: profile-select + named fields → kfAutosave (no one-off marital widget). --}}
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data"
                              data-kf-autosave
                              data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                              data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                              data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                              data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}"
                              data-kf-marital-single="{{ __('borrower.profile.marital_options.single') }}"
                              data-kf-marital-married="{{ __('borrower.profile.marital_options.married') }}"
                              data-kf-marital-divorced="{{ __('borrower.profile.marital_options.divorced') }}"
                              data-kf-marital-widowed="{{ __('borrower.profile.marital_options.widowed') }}"
                              x-data="{
                                  marital: @js(old('marital_status', $customer->marital_status) ?: ''),
                              }"
                              @profile-select="if ($event.detail && $event.detail.name === 'marital_status') marital = $event.detail.value">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="family">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <div class="space-y-4">
                                <x-site.profile-select
                                    name="marital_status"
                                    :label="__('borrower.profile.fields.marital_status')"
                                    :options="[
                                        'single' => __('borrower.profile.marital_options.single'),
                                        'married' => __('borrower.profile.marital_options.married'),
                                        'divorced' => __('borrower.profile.marital_options.divorced'),
                                        'widowed' => __('borrower.profile.marital_options.widowed'),
                                    ]"
                                    :value="old('marital_status', $customer->marital_status)"
                                    :required="true"
                                    :placeholder="__('borrower.profile.select')"
                                    :select-class="$editable"
                                />
                                <div x-show="marital === 'married'" x-cloak class="grid sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.spouse_first_name') }} <span class="text-red-500">*</span></label>
                                        <input type="text" name="spouse_first_name" value="{{ old('spouse_first_name', $customer->spouse_first_name) }}" class="{{ $editable }}" autocomplete="off" x-bind:required="marital === 'married'">
                                        @error('spouse_first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.spouse_middle_name') }}</label>
                                        <input type="text" name="spouse_middle_name" value="{{ old('spouse_middle_name', $customer->spouse_middle_name) }}" class="{{ $editable }}" autocomplete="off">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.spouse_last_name') }} <span class="text-red-500">*</span></label>
                                        <input type="text" name="spouse_last_name" value="{{ old('spouse_last_name', $customer->spouse_last_name) }}" class="{{ $editable }}" autocomplete="off" x-bind:required="marital === 'married'">
                                        @error('spouse_last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                @if ($requireMarriageCert)
                                    <div x-show="marital === 'married'" x-cloak>
                                        <x-site.profile-document-field
                                            :document="$marriageCertificate"
                                            field-name="marriage_certificate"
                                            mode="multi"
                                            :label="__('borrower.profile.marriage_certificate')"
                                            input-host-id="marriage-certificate-upload"
                                        />
                                        @error('marriage_certificate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                @endif
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.number_of_children') }} <span class="text-red-500">*</span></label>
                                    <input type="number" min="0" max="30" name="number_of_children" value="{{ old('number_of_children', $customer->number_of_children) }}" class="{{ $editable }}" required inputmode="numeric">
                                    @error('number_of_children')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Next of kin --}}
                <x-site.profile-section-card
                    @class(['hidden' => ! $showSoloCard(['kin'])])
                    section-id="profile-kin"
                    icon="👨‍👩‍👧"
                    :title="__('borrower.profile.kin_info')"
                    :complete="$kinComplete"
                    :stale="$kinStale"
                    :empty="! $kinComplete"
                    :default-open="$focusHash === 'kin'"
                    :default-edit="$editFocus === 'kin'">
                    <x-slot:view>
                        @php
                            $kinFirst = $customer->nok_first_name;
                            $kinMiddle = $customer->nok_middle_name;
                            $kinLast = $customer->nok_last_name;
                            if (! filled($kinFirst) && ! filled($kinLast) && filled($customer->nok_name)) {
                                $kinParts = preg_split('/\s+/', trim((string) $customer->nok_name)) ?: [];
                                $kinFirst = $kinParts[0] ?? '';
                                $kinLast = count($kinParts) > 1 ? array_pop($kinParts) : '';
                                array_shift($kinParts);
                                $kinMiddle = implode(' ', $kinParts);
                            }
                            $kinViewRows = [
                                ['field' => 'nok_first_name', 'label' => __('borrower.profile.fields.first_name'), 'value' => $kinFirst],
                                ['field' => 'nok_middle_name', 'label' => __('borrower.profile.fields.middle_name'), 'value' => $kinMiddle],
                                ['field' => 'nok_last_name', 'label' => __('borrower.profile.fields.last_name'), 'value' => $kinLast],
                                ['field' => 'nok_relationship', 'label' => __('borrower.profile.fields.relationship'), 'value' => $customer->nok_relationship ? kin_relationship_label($customer->nok_relationship) : null, 'label_map' => kin_relationship_options()],
                                ['field' => 'nok_phone', 'label' => __('borrower.profile.fields.phone'), 'value' => $customer->nok_phone],
                                ['field' => 'nok_region', 'label' => __('borrower.profile.region'), 'value' => $customer->nok_region],
                                ['field' => 'nok_district', 'label' => __('borrower.profile.district'), 'value' => $customer->nok_district],
                                ['field' => 'nok_ward', 'label' => __('borrower.profile.ward'), 'value' => $customer->nok_ward],
                                ['field' => 'nok_street', 'label' => __('borrower.profile.street'), 'value' => $customer->nok_street, 'span' => true],
                            ];
                            $hasAnyKinValue = collect($kinViewRows)->contains(fn ($row) => filled($row['value'] ?? null));
                        @endphp
                        @if (! $hasAnyKinValue)
                            <p class="text-sm text-gray-600">{{ __('borrower.profile.section_empty') }}</p>
                            <button type="button" @click="openEdit()" class="mt-2 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                        @else
                            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                                @foreach ($kinViewRows as $field)
                                    <div @class(['sm:col-span-2' => ! empty($field['span'])])>
                                        <dt class="text-gray-500">{{ $field['label'] }}</dt>
                                        <dd class="font-medium text-gray-900 mt-0.5"
                                            data-kf-view-field="{{ $field['field'] }}"
                                            @if (! empty($field['label_map'])) data-kf-view-label-map='@json($field['label_map'])' @endif
                                        >{{ filled($field['value']) ? $field['value'] : '—' }}</dd>
                                    </div>
                                @endforeach
                                @if (! $kinComplete)
                                    <div class="sm:col-span-2">
                                        <button type="button" @click="openEdit()" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
                                    </div>
                                @endif
                            </dl>
                        @endif
                    </x-slot:view>
                    <x-slot:form>
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
                              data-kf-autosave
                              data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                              data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                              data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                              data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="kin">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <div class="space-y-4">
                                <x-site.kin-fields :customer="$customer" :input-class="$editable" />
                                <x-site.address-fields prefix="nok" :region="old('nok_region', $customer->nok_region)" :district="old('nok_district', $customer->nok_district)" :ward="old('nok_ward', $customer->nok_ward)" :street="old('nok_street', $customer->nok_street)" />
                            </div>
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Face photos — always available on personal profile --}}
                <x-site.profile-section-card
                    @class(['hidden' => ! $showSoloCard(['face'])])
                    section-id="profile-face"
                    icon="📷"
                    :title="__('borrower.nida.face_title')"
                    :complete="$faceComplete"
                    :empty="! $faceHasPhotos && ! $faceComplete"
                    :inline-edit="true"
                    :default-open="$focusHash === 'face'"
                    :default-edit="$focusHash === 'face'"
                    :allow-overflow="true">
                    <x-slot:view>
                        @if (! empty($faceAngles ?? []))
                            <x-site.face-verification-status
                                :customer="$customer"
                                :photos="$facePhotos ?? collect()"
                                :angles="$faceAngles"
                                :compact="true"
                            />
                            <button type="button" @click="openEdit()" class="inline-flex mt-3 text-sm font-semibold text-brand hover:underline">
                                {{ __('borrower.profile.edit_face_photos') }}
                            </button>
                        @else
                            <p class="text-sm text-gray-600">{{ __('borrower.profile.section_empty') }}</p>
                            <button type="button" @click="openEdit()" class="inline-flex mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">
                                {{ __('borrower.profile.add_details') }}
                            </button>
                        @endif
                    </x-slot:view>
                    <x-slot:form>
                        @if (isset($faceSteps, $faceUploadUrls))
                            @if ($faceKey === 'revision_required')
                                <p class="text-sm text-amber-800 mb-4 font-medium">{{ __('borrower.apply.checklist.face_revision') }}</p>
                            @elseif ($faceKey === 'rejected' && filled($customer->face_rejection_notes))
                                <p class="text-sm text-red-800 mb-4">{{ $customer->face_rejection_notes }}</p>
                            @elseif (in_array($faceKey, ['verified', 'pending'], true))
                                <p class="text-sm text-gray-600 mb-4">{{ __('borrower.profile.edit_face_photos_hint') }}</p>
                            @endif
                            @include('site.borrower.profile._face_inline', [
                                'steps' => $faceSteps,
                                'uploadUrls' => $faceUploadUrls,
                                'deleteUrls' => $faceDeleteUrls ?? [],
                                'wizard' => $faceWizard ?? ['current_index' => 0],
                            ])
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
                    @class(['hidden' => ! $showSoloCard(['signature'])])
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
                            <div class="rounded-2xl bg-gradient-to-br from-brand/5 via-white to-brand-muted/20 ring-1 ring-brand/15 px-4 py-4 sm:px-5 sm:py-5">
                                <div class="flex flex-col sm:flex-row sm:items-stretch gap-4">
                                    <div class="rounded-xl bg-white ring-1 ring-gray-200/90 shadow-sm px-4 py-3 flex items-center justify-center min-h-[6.5rem] sm:min-w-[9.5rem] sm:max-w-[11rem]">
                                        <img src="{{ $customer->legal_signature_data }}" alt="" class="max-h-24 w-full object-contain">
                                    </div>
                                    <div class="min-w-0 flex-1 flex flex-col justify-center">
                                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.profile.legal_signature') }}</p>
                                        <p class="text-base font-semibold text-gray-900 mt-1">{{ $customer->legal_signer_name ?: $customer->full_name }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('borrower.profile.legal_signature_saved_at', ['date' => optional($customer->legal_signed_at)->format('d M Y') ?? '—']) }}</p>
                                        <button type="button" @click="openEdit()" class="inline-flex self-start mt-3 items-center gap-1.5 rounded-full bg-white ring-1 ring-brand/20 px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/5">
                                            {{ __('borrower.profile.replace_document') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-gray-600">{{ __('borrower.profile.legal_signature_empty') }}</p>
                            <p class="text-xs text-gray-500 mt-2">{{ __('borrower.profile.legal_signature_notice') }}</p>
                        @endif
                    </x-slot:view>
                    <x-slot:form>
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" class="space-y-4"
                              data-kf-autosave
                              data-kf-autosave-saving="{{ __('borrower.document_upload.saving') }}"
                              data-kf-autosave-saved="{{ __('borrower.document_upload.saved') }}"
                              data-kf-autosave-fail="{{ __('borrower.document_upload.could_not_save') }}"
                              data-kf-autosave-retry="{{ __('borrower.document_upload.retry') }}"
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
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>
            </div>
        @endif

        @unless (request()->boolean('solo'))
            @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'nida'])
        @endunless
    </div>
</x-site.borrower-layout>
