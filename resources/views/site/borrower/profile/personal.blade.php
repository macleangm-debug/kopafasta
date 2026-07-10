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
            $locked = (bool) $customer->identity_locked;
            $nidaSaved = filled($customer->national_id);
            $nidaReadonly = $locked || $nidaSaved;
            $editing = ($wizardMode ?? false) || ($editing ?? false);
            $requireIdentityDuringProfile = app(\App\Services\ProfileCompletionService::class)->identityRequiredDuringProfile();
            $nidaDocs = $nidaDocuments ?? collect();
            $nidaFront = $nidaDocs->get('national_id_front');
            $nidaBack = $nidaDocs->get('national_id_back');
            $uploadsComplete = app(\App\Services\ProfileValidationService::class)->nationalIdUploadsComplete($customer);
            $hasIdentity = $nidaSaved && (
                ! $requireIdentityDuringProfile || $uploadsComplete
            );
            $readonly = 'w-full rounded-lg border-gray-200 bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-sm';
            $editable = 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm';
            $hasContact = filled($customer->phone) || filled($customer->email);
            $kinComplete = app(\App\Services\ProfileValidationService::class)->isKinComplete($customer);
            $kinName = $customer->nok_name ?: trim(($customer->nok_first_name ?? '').' '.($customer->nok_last_name ?? ''));
            $faceKey = $customer->face_verification_status ?? 'incomplete';
            $faceComplete = in_array($faceKey, ['verified', 'pending'], true);
            $faceHasPhotos = ($facePhotos ?? collect())->isNotEmpty();
            $focusHash = request()->query('focus');
            $saveConfirm = [
                'title' => __('borrower.profile.save_confirm_title'),
                'message' => __('borrower.profile.save_confirm_message'),
                'confirmLabel' => __('borrower.profile.save'),
                'confirmClass' => 'bg-amber-500 hover:bg-amber-400 text-gray-900',
            ];
        @endphp

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @include('site.borrower.profile._nida_result', ['customer' => $customer])

        @if ($customer->nida_locked_until && now()->lt($customer->nida_locked_until))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900">
                <p class="font-semibold">{{ __('borrower.nida.verification_locked_banner', ['time' => $customer->nida_locked_until->timezone(config('app.timezone'))->format('d M Y H:i')]) }}</p>
                <p class="mt-1">{{ __('borrower.nida.verification_locked_appeal') }}</p>
                <a href="{{ route('site.borrower.support') }}" class="inline-flex mt-2 text-xs font-semibold text-red-800 underline">{{ __('borrower.nida.verification_locked_support') }}</a>
            </div>
        @endif

        @if (($wizardMode ?? false) && ($wizardKey ?? 'nida') === 'kin')
            <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal', 'wizard' => 1]) }}{{ ! empty($returnUrl) ? '&return='.urlencode($returnUrl) : '' }}" class="glass-card p-6 space-y-8"
                  @submit.prevent="window.confirmForm($el, @js($saveConfirm))">
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
                    :default-open="$focusHash === 'identity'">
                    <x-slot:view>
                        @if ($nidaSaved)
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-lg font-mono font-semibold text-gray-900">{{ $customer->national_id }}</p>
                                    <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                        <span aria-hidden="true">🔒</span>{{ $locked ? __('borrower.nida.locked_title') : __('borrower.nida.saved_locked_title') }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $locked ? __('borrower.nida.locked_hint') : __('borrower.nida.saved_locked_hint') }}</p>
                                </div>
                            </div>
                            <dl class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.nida_front') }}</dt>
                                    <dd class="mt-1 font-semibold {{ $nidaFront ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ $nidaFront ? __('borrower.profile.uploaded') : __('borrower.profile.missing') }}
                                    </dd>
                                </div>
                                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.nida_back') }}</dt>
                                    <dd class="mt-1 font-semibold {{ $nidaBack ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ $nidaBack ? __('borrower.profile.uploaded') : __('borrower.profile.missing') }}
                                    </dd>
                                </div>
                            </dl>
                        @else
                            <p class="text-sm text-gray-500">{{ __('borrower.profile.section_empty') }}</p>
                            <button type="button" @click="open = true" class="mt-2 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.profile.add_details') }}</button>
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
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data"
                              @submit.prevent="window.confirmForm($el, @js($saveConfirm))">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="identity">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.nida.number') }}</label>
                                    <x-site.nida-input name="national_id" :value="old('national_id', $customer->national_id)" :required="! $nidaReadonly" @if($nidaReadonly) readonly @endif />
                                    <p class="text-[11px] text-gray-400 mt-1">{{ __('borrower.nida.format_hint') }}</p>
                                    @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <x-site.profile-document-field :document="$nidaFront" field-name="national_id_front" mode="single" :label="__('borrower.profile.nida_front')" input-host-id="nida-front-upload" :required="! $nidaFront" />
                                @error('national_id_front')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                <x-site.profile-document-field :document="$nidaBack" field-name="national_id_back" mode="single" :label="__('borrower.profile.nida_back')" input-host-id="nida-back-upload" :required="! $nidaBack" />
                                @error('national_id_back')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                                {{ __('borrower.profile.save') }}
                            </button>
                        </form>
                        @unless ($locked)
                            <form method="POST" action="{{ route('site.borrower.profile.nida.verify') }}" class="mt-4 pt-4 border-t border-gray-100"
                                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.nida.verify_button')), message: @js(__('borrower.nida.subtitle')), confirmLabel: @js(__('borrower.nida.verify_button')), confirmClass: 'bg-brand hover:bg-brand-light text-white' })">
                                @csrf
                                <div class="mb-3">
                                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.nida.number') }}</label>
                                    <x-site.nida-input name="national_id" :value="old('national_id', $customer->national_id)" :required="true" @if($nidaReadonly) readonly @endif />
                                </div>
                                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.nida.subtitle') }}</p>
                                <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                                    {{ __('borrower.nida.verify_button') }}
                                </button>
                            </form>
                        @endunless
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Contact --}}
                <x-site.profile-section-card
                    section-id="profile-contact"
                    icon="📱"
                    :title="__('borrower.profile.contact_details')"
                    :complete="$hasContact"
                    :empty="! $hasContact"
                    :default-open="$focusHash === 'contact'">
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
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
                              @submit.prevent="window.confirmForm($el, @js($saveConfirm))">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="contact">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <div class="grid sm:grid-cols-2 gap-4">
                                <x-site.phone-input name="phone" :label="__('borrower.profile.fields.phone')" :value="old('phone', $customer->phone)" :input-class="$editable" />
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }}</label>
                                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="{{ $editable }}">
                                </div>
                            </div>
                            <button type="submit" class="mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                                {{ __('borrower.profile.save') }}
                            </button>
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Next of kin --}}
                <x-site.profile-section-card
                    section-id="profile-kin"
                    icon="👨‍👩‍👧"
                    :title="__('borrower.profile.kin_info')"
                    :complete="$kinComplete"
                    :empty="! $kinComplete"
                    :default-open="$focusHash === 'kin'">
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
                        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
                              @submit.prevent="window.confirmForm($el, @js($saveConfirm))">
                            @csrf @method('PUT')
                            <input type="hidden" name="focus" value="kin">
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <div class="space-y-4">
                                <x-site.kin-fields :customer="$customer" :input-class="$editable" />
                                <x-site.address-fields prefix="nok" :region="old('nok_region', $customer->nok_region)" :district="old('nok_district', $customer->nok_district)" :ward="old('nok_ward', $customer->nok_ward)" :street="old('nok_street', $customer->nok_street)" />
                            </div>
                            <button type="submit" class="mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                                {{ __('borrower.profile.save') }}
                            </button>
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>

                {{-- Face capture --}}
                @if ($requireIdentityDuringProfile || app(\App\Services\IdentityVerificationPolicyService::class)->facialRequired())
                <x-site.profile-section-card
                    section-id="profile-face"
                    icon="📷"
                    :title="__('borrower.nida.face_title')"
                    :complete="$faceComplete"
                    :empty="! $faceHasPhotos"
                    :inline-edit="! in_array($faceKey, ['verified', 'pending'], true)"
                    :default-open="$focusHash === 'face'">
                    <x-slot:view>
                        @if ($faceHasPhotos && ! empty($faceAngles ?? []))
                            <x-site.face-verification-status
                                :customer="$customer"
                                :photos="$facePhotos"
                                :angles="$faceAngles"
                                :compact="true"
                            />
                        @else
                            @php
                                $faceStatus = match ($faceKey) {
                                    'verified' => [__('borrower.nida.face_status.verified'), 'text-emerald-700'],
                                    'pending'  => [__('borrower.nida.face_status.submitted'), 'text-sky-700'],
                                    'rejected' => [__('borrower.nida.face_status.failed'), 'text-red-700'],
                                    default    => [__('borrower.nida.face_status.incomplete'), 'text-amber-700'],
                                };
                            @endphp
                            <p class="text-sm font-semibold {{ $faceStatus[1] }}">{{ $faceStatus[0] }}</p>
                            <p class="text-xs text-gray-500 mt-2">{{ __('borrower.profile.face_angles_hint') }}</p>
                        @endif
                    </x-slot:view>
                    <x-slot:form>
                        @if (in_array($faceKey, ['verified', 'pending'], true))
                            <x-site.face-verification-status
                                :customer="$customer"
                                :photos="$facePhotos ?? collect()"
                                :angles="$faceAngles ?? []"
                                :compact="true"
                            />
                        @elseif (isset($faceSteps, $faceUploadUrls))
                            @include('site.borrower.profile._face_inline', [
                                'steps' => $faceSteps,
                                'uploadUrls' => $faceUploadUrls,
                                'deleteUrls' => $faceDeleteUrls ?? [],
                                'wizard' => $faceWizard ?? ['current_index' => 0],
                            ])
                        @else
                            <p class="text-sm text-gray-600">{{ __('borrower.nida.face_capture_hint') }}</p>
                            <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex mt-3 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">
                                {{ __('borrower.nida.face_complete') }}
                            </a>
                        @endif
                    </x-slot:form>
                </x-site.profile-section-card>
                @endif
            </div>
        @endif

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'nida'])
    </div>

    @stack('scripts')
</x-site.borrower-layout>
