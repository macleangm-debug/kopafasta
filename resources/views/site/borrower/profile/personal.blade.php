<x-site.borrower-layout :title="brand_title(__('borrower.profile.account_title'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.account_title'),
            'subtitle' => __('borrower.profile.subtitle'),
            'customer' => $customer,
            'active' => 'personal',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'nida',
        ])

        @php
            $locked = (bool) $customer->identity_locked;
            $editing = ($wizardMode ?? false) || ($editing ?? false);
            $requireIdentityDuringProfile = app(\App\Services\ProfileCompletionService::class)->identityRequiredDuringProfile();
            $nidaService = app(\App\Services\NidaVerificationService::class);
            $nidaLocked = $nidaService->isLocked($customer);
            $nidaLockMessage = $nidaService->lockMessage($customer);
            $nidaStatus = $customer->nida_verification_status ?? 'unverified';
            $nidaResult = session('nida_result');
            $nameMismatch = app(\App\Services\NidaVerificationService::class)->nameMismatch($customer);
            $nidaBadge = match ($nidaStatus) {
                'verified'       => [__('borrower.nida.status.verified'), 'bg-emerald-100 text-emerald-800'],
                'name_mismatch'  => [__('borrower.nida.status.name_mismatch'), 'bg-amber-100 text-amber-800'],
                'multihit'       => [__('borrower.nida.status.multihit'), 'bg-sky-100 text-sky-800'],
                'failed'         => [__('borrower.nida.status.failed'), 'bg-red-100 text-red-800'],
                default          => [__('borrower.nida.status.unverified'), 'bg-amber-100 text-amber-800'],
            };
            $crbCandidates = session('crb_candidates') ?? ($kyc->payload['crb_candidates'] ?? []);
            $searchRequestId = session('crb_search_request_id') ?? ($kyc->payload['crb_search_request_id'] ?? null);
            $confirmNationalId = old('national_id', $customer->national_id ?: ($kyc->payload['nida_verification_attempt']['national_id'] ?? ''));
            $readonly = 'w-full rounded-lg border-gray-200 bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-sm';
            $editable = 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm';
        @endphp

        @if ($requireIdentityDuringProfile && (! ($wizardMode ?? false) || ($wizardKey ?? 'nida') !== 'kin'))
        {{-- NIDA verification card --}}
        <div class="glass-card p-6 mb-6" x-data="{ submitting: false }">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-semibold">{{ __('borrower.nida.title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.nida.subtitle') }}</p>
                </div>
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $nidaBadge[1] }}">{{ $nidaBadge[0] }}</span>
            </div>

            @if ($nidaLocked)
                <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-4 text-sm text-red-900">
                    <p class="font-semibold">{{ __('borrower.nida.result.locked_title') }}</p>
                    <p class="mt-1">{{ $nidaLockMessage ?? __('borrower.nida.result.locked_default') }}</p>
                    <p class="mt-3 text-red-800">{{ __('borrower.nida.verification_locked_appeal') }}</p>
                    <a href="{{ route('site.borrower.support') }}#identity-appeal"
                       class="inline-flex mt-3 bg-white ring-1 ring-red-200 hover:bg-red-50 text-red-900 font-semibold px-4 py-2 rounded-full text-sm">
                        {{ __('borrower.nida.verification_locked_support') }}
                    </a>
                </div>
            @endif

            @if ($nidaResult)
                @php $resultStatus = $nidaResult['status'] ?? 'failed'; @endphp
                <div class="mb-4 rounded-xl px-4 py-4 text-sm ring-1
                    {{ $resultStatus === 'verified' ? 'bg-emerald-50 ring-emerald-200 text-emerald-900' : '' }}
                    {{ $resultStatus === 'name_mismatch' ? 'bg-amber-50 ring-amber-200 text-amber-900' : '' }}
                    {{ in_array($resultStatus, ['failed', 'multihit'], true) ? 'bg-red-50 ring-red-200 text-red-900' : '' }}
                    {{ $resultStatus === 'in_progress' ? 'bg-sky-50 ring-sky-200 text-sky-900' : '' }}
                    {{ $resultStatus === 'locked' ? 'bg-red-50 ring-red-200 text-red-900' : '' }}">
                    @if ($resultStatus === 'verified')
                        <p class="font-semibold">{{ __('borrower.nida.result.verified_title') }}</p>
                        <p class="mt-1">{{ __('borrower.nida.result.verified_body') }}</p>
                    @elseif ($resultStatus === 'name_mismatch')
                        <p class="font-semibold">{{ ($nidaResult['level'] ?? 1) >= 2 ? __('borrower.nida.result.mismatch_important') : __('borrower.nida.result.mismatch_detected') }}</p>
                        <p class="mt-1">{{ $nidaResult['message'] ?? __('borrower.nida.result.mismatch_default') }}</p>
                    @elseif ($resultStatus === 'locked')
                        <p class="font-semibold">{{ __('borrower.nida.result.locked_title') }}</p>
                        <p class="mt-1">{{ $nidaResult['message'] ?? __('borrower.nida.result.locked_default') }}</p>
                    @elseif ($resultStatus === 'multihit')
                        <p class="font-semibold">{{ __('borrower.nida.result.multihit_title') }}</p>
                        <p class="mt-1">{{ $nidaResult['message'] ?? __('borrower.nida.result.multihit_default') }}</p>
                    @else
                        <p class="font-semibold">{{ __('borrower.nida.result.failed_title') }}</p>
                        <p class="mt-1">{{ $nidaResult['message'] ?? __('borrower.nida.result.failed_default') }}</p>
                    @endif
                </div>
            @endif

            @if ($nameMismatch && ! $nidaLocked)
                @php
                    $remainingAttempts = app(\App\Services\NidaVerificationService::class)->remainingMismatchAttempts($customer);
                    $maxAttempts = app(\App\Services\NidaVerificationService::class)->settings()['max_mismatch_attempts'];
                    $usedAttempts = min($maxAttempts, (int) $customer->nida_mismatch_attempts);
                    $verifiedNames = $customer->kyc?->payload['nida_verified_names'] ?? [];
                @endphp
                <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
                    <p class="text-sm font-semibold text-amber-900">{{ __('borrower.nida.mismatch_title') }}</p>
                    <p class="text-sm text-amber-900 mt-2">{{ __('borrower.nida.mismatch_body') }}</p>
                    <div class="mt-3 rounded-lg bg-white/80 ring-1 ring-amber-200 px-3 py-2 text-xs text-amber-900">
                        <p>{{ __('borrower.nida.mismatch_attempts_summary', ['used' => $usedAttempts, 'max' => $maxAttempts, 'remaining' => $remainingAttempts]) }}</p>
                    </div>
                    @if (($nameMismatch['mismatches'] ?? []) !== [])
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead>
                                    <tr class="text-amber-800 border-b border-amber-200">
                                        <th class="py-2 pr-3 font-semibold">{{ __('borrower.nida.mismatch_field') }}</th>
                                        <th class="py-2 pr-3 font-semibold">{{ __('borrower.nida.mismatch_registration') }}</th>
                                        <th class="py-2 font-semibold">{{ __('borrower.nida.mismatch_nida') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($nameMismatch['mismatches'] as $row)
                                        <tr class="border-b border-amber-100">
                                            <td class="py-2 pr-3 font-medium">{{ $row['label'] }}</td>
                                            <td class="py-2 pr-3">{{ $row['registered'] ?? '—' }}</td>
                                            <td class="py-2 font-semibold text-red-800">{{ $row['verified'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if (filled($verifiedNames['full_name'] ?? null))
                        <p class="text-xs text-amber-800 mt-3">{{ __('borrower.nida.mismatch_bureau_name', ['name' => $verifiedNames['full_name']]) }}</p>
                    @endif
                    @if ($customer->nida_verification_status === 'dob_mismatch' || ($nidaStatus ?? '') === 'dob_mismatch')
                        <p class="text-xs text-red-800 mt-2 font-medium">{{ __('borrower.nida.dob_mismatch_hint') }}</p>
                    @endif
                    @if ($remainingAttempts > 0)
                        <p class="text-sm text-amber-900 mt-4 font-medium">{{ __('borrower.nida.mismatch_retry_hint', ['remaining' => $remainingAttempts]) }}</p>
                    @else
                        <p class="text-sm text-red-800 mt-4 font-medium">{{ __('borrower.nida.mismatch_locked_hint') }}</p>
                    @endif
                    <p class="text-xs text-amber-800 mt-3">{{ __('borrower.nida.mismatch_no_override') }}</p>
                </div>
            @endif

            @if (! $locked && ($crbUsesStub ?? false) && ! empty($crbSamples))
                <div class="mb-4 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
                    <p class="font-semibold">{{ __('borrower.nida.sandbox_title') }}</p>
                    <p class="text-xs text-sky-800 mt-1">{{ __('borrower.nida.sandbox_hint') }}</p>
                    <ul class="mt-3 space-y-2 text-xs">
                        @foreach ($crbSamples as $key => $sample)
                            <li class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-mono font-semibold">{{ $sample['nida'] }}</span>
                                <span class="text-sky-700">— {{ $sample['label'] ?? $key }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $locked && ! $nidaLocked && ! in_array($nidaStatus, ['name_mismatch', 'multihit'], true))
                <form method="POST" action="{{ route('site.borrower.profile.nida.verify') }}" class="space-y-4"
                      @submit="submitting = true">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.nida.number') }}</label>
                        <x-site.nida-input name="national_id" :value="old('national_id', $customer->national_id)" />
                        <p class="text-[11px] text-gray-500 mt-1">{{ __('borrower.nida.format_hint') }}</p>
                        @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" :disabled="submitting"
                            class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-800 disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                        <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="submitting ? @js(__('borrower.nida.verifying')) : @js(__('borrower.nida.verify_button'))"></span>
                    </button>
                </form>
            @elseif ($locked)
                <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-medium">{{ __('borrower.nida.locked_title') }}</p>
                    <p class="mt-1 font-mono">{{ $customer->national_id }}</p>
                    <p class="text-xs text-emerald-800 mt-2">{{ __('borrower.nida.locked_hint') }}</p>
                </div>
            @endif

            @if ($nidaStatus === 'multihit' && count($crbCandidates) > 0 && $searchRequestId && filled($confirmNationalId))
                <div class="mt-6 border-t border-gray-100 pt-5" x-data="{ confirming: null }">
                    <h3 class="text-sm font-semibold mb-1">{{ __('borrower.nida.multihit_title') }}</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ __('borrower.nida.multihit_hint') }}</p>
                    <div class="space-y-3">
                        @foreach ($crbCandidates as $candidate)
                            <form method="POST" action="{{ route('site.borrower.profile.nida.confirm') }}"
                                  class="rounded-xl ring-1 ring-gray-200 p-4 flex flex-wrap items-center justify-between gap-3"
                                  @submit="confirming = @js($candidate['entity_key'] ?? '')">
                                @csrf
                                <input type="hidden" name="national_id" value="{{ $confirmNationalId }}">
                                <input type="hidden" name="search_request_id" value="{{ $searchRequestId }}">
                                <input type="hidden" name="entity_key" value="{{ $candidate['entity_key'] ?? '' }}">
                                <div class="text-sm">
                                    <p class="font-medium">{{ $candidate['name'] ?? 'Unknown' }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ __('borrower.nida.dob_score', ['dob' => $candidate['dob'] ?? '—', 'score' => $candidate['score'] ?? '—']) }}
                                    </p>
                                </div>
                                <button type="submit" :disabled="confirming !== null"
                                        class="inline-flex items-center gap-2 text-sm font-semibold bg-amber-500 hover:bg-amber-400 disabled:opacity-60 text-gray-900 px-4 py-2 rounded-full">
                                    <svg x-show="confirming === @js($candidate['entity_key'] ?? '')" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span>{{ __('borrower.nida.this_is_me') }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @elseif ($nidaStatus === 'multihit' && (! $searchRequestId || ! filled($confirmNationalId)))
                <div class="mt-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                    <p>{{ __('borrower.nida.multihit_retry') }}</p>
                </div>
            @endif
        </div>
        @elseif (! $requireIdentityDuringProfile && ! ($wizardMode ?? false))
        <div class="glass-card p-5 mb-6">
            <p class="font-semibold text-brand">{{ __('borrower.profile.identity_deferred_title') }}</p>
            <p class="text-sm text-gray-600 mt-2">{{ __('borrower.profile.identity_deferred_body') }}</p>
        </div>
        @endif

        @php
            $nidaDocs = $nidaDocuments ?? collect();
            $nidaFront = $nidaDocs->get('national_id_front');
        @endphp

        @if (($wizardMode ?? false) && ($wizardKey ?? 'nida') === 'kin')
            <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal', 'wizard' => 1]) }}" class="glass-card p-6 space-y-8"
                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
                @csrf @method('PUT')
                <input type="hidden" name="wizard" value="1">
                <input type="hidden" name="focus" value="kin">
                <div id="next-of-kin" class="scroll-mt-24">
                    <h3 class="font-semibold mb-1">{{ __('borrower.profile.kin_info') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.kin_subtitle') }}</p>
                    <div class="space-y-4">
                        <x-site.kin-fields :customer="$customer" :input-class="$editable" />
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-3">{{ __('borrower.profile.residence') }}</p>
                            <x-site.address-fields
                                prefix="nok"
                                :region="old('nok_region', $customer->nok_region)"
                                :district="old('nok_district', $customer->nok_district)"
                                :ward="old('nok_ward', $customer->nok_ward)"
                                :street="old('nok_street', $customer->nok_street)"
                            />
                        </div>
                    </div>
                </div>
                <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.profile_wizard.save_continue') }}
                </button>
            </form>
        @elseif (! $editing && ! ($wizardMode ?? false))
            @php
                $hasContact = filled($customer->phone) || filled($customer->email);
                $kinComplete = app(\App\Services\ProfileValidationService::class)->isKinComplete($customer);
                $kinName = $customer->nok_name ?: trim(($customer->nok_first_name ?? '').' '.($customer->nok_last_name ?? ''));
            @endphp
            <x-site.profile-section-card
                :title="__('borrower.profile.contact_details')"
                :editing="false"
                :edit-url="route('site.borrower.profile', ['section' => 'personal', 'edit' => 1])"
                :complete="$hasContact"
                :empty="! $hasContact"
                :add-url="route('site.borrower.profile', ['section' => 'personal', 'edit' => 1])">
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">{{ __('borrower.profile.fields.phone') }}</dt><dd class="font-medium mt-0.5">{{ $customer->phone }}</dd></div>
                    @if (filled($customer->email) && ! str_ends_with(strtolower($customer->email), '@phone.kopafasta.local'))
                        <div><dt class="text-gray-500">{{ __('borrower.profile.fields.email') }}</dt><dd class="font-medium mt-0.5">{{ $customer->email }}</dd></div>
                    @endif
                </dl>
            </x-site.profile-section-card>

            <div class="mt-6">
            <x-site.profile-section-card
                :title="__('borrower.profile.kin_info')"
                :editing="false"
                :edit-url="route('site.borrower.profile', ['section' => 'personal', 'edit' => 1, 'focus' => 'kin']).'#next-of-kin'"
                :complete="$kinComplete"
                :empty="! $kinComplete"
                :add-url="route('site.borrower.profile', ['section' => 'personal', 'edit' => 1, 'focus' => 'kin']).'#next-of-kin'">
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">{{ __('borrower.profile.fields.full_name') }}</dt><dd class="font-medium mt-0.5">{{ $kinName }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('borrower.profile.fields.phone') }}</dt><dd class="font-medium mt-0.5">{{ $customer->nok_phone }}</dd></div>
                    @if (filled($customer->nok_region))
                        <div><dt class="text-gray-500">{{ __('borrower.profile.region') }}</dt><dd class="font-medium mt-0.5">{{ $customer->nok_region }}</dd></div>
                    @endif
                    @if (filled($customer->nok_district))
                        <div><dt class="text-gray-500">{{ __('borrower.profile.district') }}</dt><dd class="font-medium mt-0.5">{{ $customer->nok_district }}</dd></div>
                    @endif
                </dl>
            </x-site.profile-section-card>
            </div>
        @else
        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data" class="glass-card p-6 space-y-8"
              x-data="{ submitting: false }"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf @method('PUT')
            @if ($wizardMode ?? false)
                <input type="hidden" name="wizard" value="1">
                <input type="hidden" name="focus" value="{{ $wizardKey ?? 'nida' }}">
            @endif

            @if (! ($wizardMode ?? false) || ($wizardKey ?? 'nida') !== 'kin')
            <div>
            <h2 class="font-semibold mb-1">{{ __('borrower.profile.personal') }}</h2>
            <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.personal_sections_hint') }}</p>
            <dl class="grid sm:grid-cols-2 gap-x-4 gap-y-3 text-sm mb-6 pb-6 border-b border-gray-100">
                <div><dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.full_name') }}</dt><dd class="font-medium mt-0.5">{{ trim($customer->first_name.' '.($customer->middle_name ?? '').' '.$customer->last_name) ?: '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.date_of_birth') }}</dt><dd class="font-medium mt-0.5">{{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.gender') }}</dt><dd class="font-medium mt-0.5">{{ ucfirst($customer->gender ?? '—') }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.national_id') }}</dt><dd class="font-medium mt-0.5 font-mono">{{ $customer->national_id ?? '—' }}</dd></div>
            </dl>

            </div>

            @if ($requireIdentityDuringProfile)
            <div class="border-t border-gray-100 pt-6">
                <h3 class="font-semibold mb-1">{{ __('borrower.profile.nida_card_uploads') }}</h3>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.nida_front_only_hint') }}</p>
                @error('national_id_front')<p class="text-xs text-red-600 mb-2">{{ $message }}</p>@enderror
                <x-site.profile-document-field
                    :document="$nidaFront"
                    field-name="national_id_front"
                    mode="single"
                    :label="__('borrower.profile.nida_front')"
                    input-host-id="nida-front-upload"
                    :required="true"
                />
            </div>
            @endif

            <div class="border-t border-gray-100 pt-6">
            <h3 class="font-semibold mb-4">{{ __('borrower.profile.contact_details') }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <x-site.phone-input name="phone" :label="__('borrower.profile.fields.phone')" :value="old('phone', $customer->phone)" :input-class="$editable" />
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="{{ $editable }}">
                </div>
                @if ($requireIdentityDuringProfile)
                <div class="sm:col-span-2 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm">
                    @php
                        $faceKey = $customer->face_verification_status ?? 'incomplete';
                        $faceStatus = match ($faceKey) {
                            'verified' => [__('borrower.nida.face_status.verified'), 'bg-emerald-100 text-emerald-800'],
                            'pending'  => [__('borrower.nida.face_status.submitted'), 'bg-sky-100 text-sky-800'],
                            'rejected' => [__('borrower.nida.face_status.failed'), 'bg-red-100 text-red-800'],
                            default    => [__('borrower.nida.face_status.incomplete'), 'bg-amber-100 text-amber-800'],
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="font-semibold text-gray-900">{{ __('borrower.nida.face_title') }}</p>
                            <p class="text-xs text-gray-600 mt-0.5">{{ __('borrower.nida.face_capture_hint') }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $faceStatus[1] }}">{{ $faceStatus[0] }}</span>
                    </div>
                    <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex mt-3 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">
                        {{ in_array($faceKey, ['verified', 'pending'], true) ? __('borrower.nida.face_view') : __('borrower.nida.face_complete') }}
                    </a>
                </div>
                @endif
            </div>

            </div>
            @endif

            @if (! ($wizardMode ?? false) || ($wizardKey ?? 'nida') === 'kin')
            <div id="next-of-kin" class="border-t border-gray-100 pt-6 scroll-mt-24">
                <h3 class="font-semibold mb-1">{{ __('borrower.profile.kin_info') }}</h3>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.kin_subtitle') }}</p>
                <div class="space-y-4">
                    <x-site.kin-fields :customer="$customer" :input-class="$editable" />
                    <div>
                        <p class="text-xs font-medium text-gray-600 mb-3">{{ __('borrower.profile.residence') }}</p>
                        <x-site.address-fields
                            prefix="nok"
                            :region="old('nok_region', $customer->nok_region)"
                            :district="old('nok_district', $customer->nok_district)"
                            :ward="old('nok_ward', $customer->nok_ward)"
                            :street="old('nok_street', $customer->nok_street)"
                        />
                    </div>
                </div>
            </div>
            @endif

            <button type="submit" :disabled="submitting"
                    class="mt-6 inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 disabled:opacity-60 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>{{ ($wizardMode ?? false) ? __('borrower.profile_wizard.save_continue') : __('borrower.profile.save_personal') }}</span>
            </button>
        </form>
        @endif

        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => $wizardMode ?? false, 'wizardKey' => $wizardKey ?? 'nida'])
    </div>

    @stack('scripts')
</x-site.borrower-layout>
