<x-site.borrower-layout :title="brand_title('Profile')" active="profile">

    <div>
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.profile.subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'personal'])
        @include('site.borrower.profile._kyc_progress', ['customer' => $customer, 'active' => 'personal'])

        @include('site.borrower.profile._completion')

        @php
            $locked = (bool) $customer->identity_locked;
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
            $searchRequestId = $kyc->payload['crb_search_request_id'] ?? null;
            $readonly = 'w-full rounded-lg border-gray-200 bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-sm';
            $editable = 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm';
        @endphp

        {{-- NIDA verification card --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6" x-data="{ submitting: false }">
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
                <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
                    <p class="text-sm font-semibold text-amber-900">{{ __('borrower.nida.mismatch_title') }}</p>
                    <p class="text-sm text-amber-900 mt-2">{{ __('borrower.nida.mismatch_hidden_bureau') }}</p>
                    @if (($nameMismatch['mismatches'] ?? []) !== [])
                        <ul class="mt-3 space-y-1 text-xs text-amber-800">
                            @foreach ($nameMismatch['mismatches'] as $row)
                                <li>{{ $row['label'] }}: {{ $row['registered'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="text-sm text-amber-900 mt-4">{{ __('borrower.nida.mismatch_no_override') }}</p>
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

            @if (! $locked && ! $nidaLocked && $nidaStatus !== 'name_mismatch')
                <form method="POST" action="{{ route('site.borrower.profile.nida.verify') }}" class="space-y-4"
                      @submit="submitting = true">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.nida.number') }}</label>
                        <input name="national_id" value="{{ old('national_id', $customer->national_id) }}" required
                               placeholder="XXXXXXXX-XXXXX-XXXXX-XX"
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
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

            @if ($nidaStatus === 'multihit' && count($crbCandidates) > 0 && $searchRequestId)
                <div class="mt-6 border-t border-gray-100 pt-5">
                    <h3 class="text-sm font-semibold mb-3">{{ __('borrower.nida.multihit_title') }}</h3>
                    <div class="space-y-3">
                        @foreach ($crbCandidates as $candidate)
                            <form method="POST" action="{{ route('site.borrower.profile.nida.confirm') }}" class="rounded-xl ring-1 ring-gray-200 p-4 flex flex-wrap items-center justify-between gap-3">
                                @csrf
                                <input type="hidden" name="national_id" value="{{ $customer->national_id }}">
                                <input type="hidden" name="search_request_id" value="{{ $searchRequestId }}">
                                <input type="hidden" name="entity_key" value="{{ $candidate['entity_key'] ?? '' }}">
                                <div class="text-sm">
                                    <p class="font-medium">{{ $candidate['name'] ?? 'Unknown' }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ __('borrower.nida.dob_score', ['dob' => $candidate['dob'] ?? '—', 'score' => $candidate['score'] ?? '—']) }}
                                    </p>
                                </div>
                                <button class="text-sm font-semibold bg-amber-500 hover:bg-amber-400 text-gray-900 px-4 py-2 rounded-full">{{ __('borrower.nida.this_is_me') }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}" class="bg-white rounded-2xl border border-gray-200 p-6"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf @method('PUT')

            <h2 class="font-semibold mb-1">{{ __('borrower.profile.personal_info') }}</h2>
            <p class="text-xs text-gray-500 mb-4">{{ __('borrower.profile.personal_info_hint') }}</p>
            <dl class="grid sm:grid-cols-2 gap-x-4 gap-y-3 text-sm mb-6 pb-6 border-b border-gray-100">
                <div><dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.full_name') }}</dt><dd class="font-medium mt-0.5">{{ trim($customer->first_name.' '.($customer->middle_name ?? '').' '.$customer->last_name) ?: '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.date_of_birth') }}</dt><dd class="font-medium mt-0.5">{{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.gender') }}</dt><dd class="font-medium mt-0.5">{{ ucfirst($customer->gender ?? '—') }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.national_id') }}</dt><dd class="font-medium mt-0.5 font-mono">{{ $customer->national_id ?? '—' }}</dd></div>
            </dl>

            <h3 class="font-semibold mb-4">{{ __('borrower.profile.contact_details') }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }}</label>
                    <input name="phone" value="{{ old('phone', $customer->phone) }}" class="{{ $editable }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="{{ $editable }}">
                </div>
                <div class="sm:col-span-2 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm">
                    @php
                        $faceKey = $customer->face_verification_status ?? 'incomplete';
                        $faceStatus = match ($faceKey) {
                            'verified' => [__('borrower.nida.face_status.verified'), 'bg-emerald-100 text-emerald-800'],
                            'pending'  => [__('borrower.nida.face_status.pending'), 'bg-sky-100 text-sky-800'],
                            'rejected' => [__('borrower.nida.face_status.rejected'), 'bg-red-100 text-red-800'],
                            default    => [__('borrower.nida.face_status.incomplete'), 'bg-amber-100 text-amber-800'],
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="font-medium text-gray-900">{{ __('borrower.nida.face_title') }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.nida.face_required') }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $faceStatus[1] }}">{{ $faceStatus[0] }}</span>
                    </div>
                    <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">
                        {{ ($customer->face_verification_status ?? 'incomplete') === 'verified' ? __('borrower.nida.face_view') : __('borrower.nida.face_complete') }}
                    </a>
                </div>
            </div>

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                {{ __('borrower.profile.save_contact') }}
            </button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
