@php
    $nidaResult = session('nida_result');
    $candidates = session('crb_candidates', []);
    $searchRequestId = session('crb_search_request_id');
@endphp

@if ($nidaResult)
    @php $status = $nidaResult['status'] ?? 'failed'; @endphp
    @if ($status === 'verified')
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
            <p class="font-semibold">{{ __('borrower.nida.result.verified_title') }}</p>
            <p class="mt-1 text-emerald-800">{{ __('borrower.nida.result.verified_body') }}</p>
        </div>
    @elseif ($status === 'multihit')
        <div class="mb-4 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 text-sm text-sky-950">
            <p class="font-semibold">{{ __('borrower.nida.result.multihit_title') }}</p>
            <p class="mt-1 text-sky-900/90">{{ $nidaResult['message'] ?? __('borrower.nida.result.multihit_default') }}</p>
            @if (is_array($candidates) && $candidates !== [] && filled($searchRequestId))
                <div class="mt-4 space-y-2">
                    @foreach ($candidates as $candidate)
                        @php
                            $entityKey = $candidate['entity_key'] ?? $candidate['id'] ?? null;
                            $name = $candidate['full_name'] ?? trim(($candidate['first_name'] ?? '').' '.($candidate['last_name'] ?? ''));
                            $nidaNumber = $candidate['national_id'] ?? $customer->national_id;
                        @endphp
                        @if ($entityKey)
                            <form method="POST" action="{{ route('site.borrower.profile.nida.confirm') }}" class="rounded-xl bg-white/80 ring-1 ring-sky-200 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                                @csrf
                                <input type="hidden" name="national_id" value="{{ $nidaNumber }}">
                                <input type="hidden" name="search_request_id" value="{{ $searchRequestId }}">
                                <input type="hidden" name="entity_key" value="{{ $entityKey }}">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $name ?: __('borrower.nida.multihit_title') }}</p>
                                    @if (! empty($candidate['date_of_birth']))
                                        <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.nida.dob_score', ['dob' => $candidate['date_of_birth'], 'score' => $candidate['score'] ?? '—']) }}</p>
                                    @endif
                                </div>
                                <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-full text-xs">
                                    {{ __('borrower.nida.this_is_me') }}
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($status === 'name_mismatch' || ($status === 'failed' && str_contains(strtolower((string) ($nidaResult['message'] ?? '')), 'mismatch')))
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">{{ __('borrower.nida.result.mismatch_important') }}</p>
            <p class="mt-1">{{ $nidaResult['message'] ?? __('borrower.nida.result.mismatch_default') }}</p>
        </div>
    @elseif ($status === 'locked')
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900">
            <p class="font-semibold">{{ __('borrower.nida.result.locked_title') }}</p>
            <p class="mt-1">{{ $nidaResult['message'] ?? __('borrower.nida.result.locked_default') }}</p>
        </div>
    @else
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900">
            <p class="font-semibold">{{ __('borrower.nida.result.failed_title') }}</p>
            <p class="mt-1">{{ $nidaResult['message'] ?? __('borrower.nida.result.failed_default') }}</p>
        </div>
    @endif
@endif
