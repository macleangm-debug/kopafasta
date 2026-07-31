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
            <div class="mb-6 rounded-2xl bg-amber-50 ring-1 ring-amber-200/80 p-5">
                <p class="text-sm font-bold text-amber-950">{{ __('borrower.profile.gaps.banner_title') }}</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($personalGaps as $gap)
                        <li>
                            <a href="{{ $gap['url'] }}" class="text-sm font-semibold text-brand hover:underline">• {{ $gap['label'] }} →</a>
                        </li>
                    @endforeach
                </ul>
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
                            <dl class="mt-4 grid sm:grid-cols-2 gap-3 text-sm" x-data="{ expandedUrl: null }">
                                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.nida_front') }}</dt>
                                    <dd class="mt-2">
                                        @if ($nidaFront?->file_path)
                                            @php $frontUrl = asset('storage/'.$nidaFront->file_path); @endphp
                                            <button type="button" @click="expandedUrl = @js($frontUrl)"
                                                    class="h-24 w-full max-w-[7rem] rounded-lg ring-1 ring-gray-200 overflow-hidden bg-white cursor-zoom-in block"
                                                    title="{{ __('borrower.profile.view_document') }}">
                                                <img src="{{ $frontUrl }}" alt="" class="h-full w-full object-cover object-center">
                                            </button>
                                        @else
                                            <span class="font-semibold text-amber-700">{{ __('borrower.profile.missing') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-3">
                                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.nida_back') }}</dt>
                                    <dd class="mt-2">
                                        @if ($nidaBack?->file_path)
                                            @php $backUrl = asset('storage/'.$nidaBack->file_path); @endphp
                                            <button type="button" @click="expandedUrl = @js($backUrl)"
                                                    class="h-24 w-full max-w-[7rem] rounded-lg ring-1 ring-gray-200 overflow-hidden bg-white cursor-zoom-in block"
                                                    title="{{ __('borrower.profile.view_document') }}">
                                                <img src="{{ $backUrl }}" alt="" class="h-full w-full object-cover object-center">
                                            </button>
                                        @else
                                            <span class="font-semibold text-amber-700">{{ __('borrower.profile.missing') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div x-show="expandedUrl" x-cloak x-transition
                                     class="fixed inset-0 z-[80] bg-black/70 flex items-center justify-center p-4"
                                     @keydown.escape.window="expandedUrl = null"
                                     @click.self="expandedUrl = null">
                                    <button type="button" class="absolute top-4 right-4 text-white/90 text-sm font-semibold" @click="expandedUrl = null">{{ __('borrower.profile.cancel') }}</button>
                                    <img :src="expandedUrl" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl">
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

                {{-- Face capture — always inline-edit like signature so Complete still shows previews + Edit --}}
                @if ($requireIdentityDuringProfile || app(\App\Services\IdentityVerificationPolicyService::class)->facialRequired())
                <x-site.profile-section-card
                    section-id="profile-face"
                    icon="📷"
                    :title="__('borrower.nida.face_title')"
                    :complete="$faceComplete"
                    :empty="! $faceHasPhotos && ! $faceComplete"
                    :inline-edit="true"
                    :default-open="$focusHash === 'face' || in_array($faceKey, ['rejected', 'revision_required'], true)"
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
                            <p class="text-xs text-gray-500 mt-2">{{ __('borrower.profile.face_angles_hint') }}</p>
                            <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">
                                {{ __('borrower.nida.face_replace') }}
                            </a>
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
                            @if ($faceKey === 'pending')
                                <p class="text-xs text-gray-500 mt-3">{{ __('borrower.nida.face_submitted_body') }}</p>
                            @elseif ($faceKey === 'verified')
                                <p class="text-xs text-gray-500 mt-3">{{ __('borrower.nida.face_replace_hint') }}</p>
                            @endif
                        @else
                            <p class="text-sm text-gray-600">{{ __('borrower.nida.face_capture_hint') }}</p>
                            <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex mt-3 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">
                                {{ __('borrower.nida.face_complete') }}
                            </a>
                        @endif
                    </x-slot:form>
                </x-site.profile-section-card>
                @endif

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
                    :default-open="$focusHash === 'signature'">
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
                              @submit.prevent="
                                  const pad = $el.querySelector('[data-signature-pad]');
                                  const alpine = pad && window.Alpine ? Alpine.$data(pad) : null;
                                  if (alpine) {
                                      const hidden = $el.querySelector('[name=signature_data]');
                                      if (hidden) hidden.value = alpine.dataUrl || '';
                                  }
                                  window.confirmForm($el, @js($saveConfirm));
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
                            <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                                {{ __('borrower.profile.save') }}
                            </button>
                        </form>
                    </x-slot:form>
                </x-site.profile-section-card>
            </div>
        @endif

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'nida'])
    </div>
</x-site.borrower-layout>
