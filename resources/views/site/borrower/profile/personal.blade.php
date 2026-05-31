<x-site.borrower-layout title="Profile — Kopafasta" active="profile">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">Profile</h1>
        <p class="text-sm text-gray-500 mb-6">Keep your personal, activity, residence and KYC details up to date.</p>

        @include('site.borrower.profile._tabs', ['active' => 'personal'])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @php
            $locked = (bool) $customer->identity_locked;
            $nidaStatus = $customer->nida_verification_status ?? 'unverified';
            $nidaBadge = match ($nidaStatus) {
                'verified'  => ['Verified via CRB', 'bg-emerald-100 text-emerald-800'],
                'multihit'  => ['Select match', 'bg-sky-100 text-sky-800'],
                'failed'    => ['Verification failed', 'bg-red-100 text-red-800'],
                default     => ['Not verified', 'bg-amber-100 text-amber-800'],
            };
            $crbCandidates = session('crb_candidates') ?? ($kyc->payload['crb_candidates'] ?? []);
            $searchRequestId = $kyc->payload['crb_search_request_id'] ?? null;
            $readonly = 'w-full rounded-lg border-gray-200 bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-sm';
            $editable = 'w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm';
        @endphp

        <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-semibold">NIDA verification</h2>
                    <p class="text-sm text-gray-600 mt-1">We verify your NIDA number through the Tanzania Credit Bureau (D&amp;B Live Request).</p>
                </div>
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $nidaBadge[1] }}">{{ $nidaBadge[0] }}</span>
            </div>

            @if (! $locked && ($crbUsesStub ?? false) && ! empty($crbSamples))
                <div class="mb-4 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
                    <p class="font-semibold">Sandbox test NIDA samples</p>
                    <p class="text-xs text-sky-800 mt-1">Stub mode is on — use these numbers to test CRB flows without live bureau credentials.</p>
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

            @if (! $locked)
                <form method="POST" action="{{ route('site.borrower.profile.nida.verify') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">NIDA number</label>
                        <input name="national_id" value="{{ old('national_id', $customer->national_id) }}" required
                               placeholder="XXXXXXXX-XXXXX-XXXXX-XX"
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
                        @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                        Verify with CRB
                    </button>
                </form>
            @else
                <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-medium">Identity verified</p>
                    <p class="mt-1 font-mono">{{ $customer->national_id }}</p>
                    <p class="text-xs text-emerald-800 mt-2">Name, date of birth and gender are locked after CRB verification.</p>
                </div>
            @endif

            @if ($nidaStatus === 'multihit' && count($crbCandidates) > 0 && $searchRequestId)
                <div class="mt-6 border-t border-gray-100 pt-5">
                    <h3 class="text-sm font-semibold mb-3">Multiple CRB matches — select your record</h3>
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
                                        DOB: {{ $candidate['dob'] ?? '—' }} · Score: {{ $candidate['score'] ?? '—' }}%
                                    </p>
                                </div>
                                <button class="text-sm font-semibold bg-amber-500 hover:bg-amber-400 text-gray-900 px-4 py-2 rounded-full">This is me</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}" class="bg-white rounded-2xl border border-gray-200 p-6">
            @csrf @method('PUT')

            <h2 class="font-semibold mb-4">Personal information</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">First name</label>
                    <input name="first_name" value="{{ old('first_name', $customer->first_name) }}" @required(! $locked)
                           @readonly($locked) class="{{ $locked ? $readonly : $editable }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Middle name</label>
                    <input name="middle_name" value="{{ old('middle_name', $customer->middle_name) }}" @readonly($locked)
                           class="{{ $locked ? $readonly : $editable }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Last name</label>
                    <input name="last_name" value="{{ old('last_name', $customer->last_name) }}" @required(! $locked)
                           @readonly($locked) class="{{ $locked ? $readonly : $editable }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Phone</label>
                    <input name="phone" value="{{ old('phone', $customer->phone) }}" class="{{ $editable }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="{{ $editable }}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Date of birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d')) }}"
                           @required(! $locked) @readonly($locked) class="{{ $locked ? $readonly : $editable }}">
                    @unless($locked)
                        <p class="text-[11px] text-gray-500 mt-1">Must be 18 years or older (BOT compliance).</p>
                    @endunless
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Gender</label>
                    @if ($locked)
                        <input value="{{ ucfirst($customer->gender ?? '') }}" readonly class="{{ $readonly }}">
                    @else
                        <select name="gender" class="{{ $editable }}">
                            <option value="">Select</option>
                            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('gender', $customer->gender) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="sm:col-span-2 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm">
                    @php
                        $faceStatus = match ($customer->face_verification_status ?? 'incomplete') {
                            'verified' => ['Verified', 'bg-emerald-100 text-emerald-800'],
                            'pending'  => ['Pending review', 'bg-sky-100 text-sky-800'],
                            'rejected' => ['Rejected — re-upload', 'bg-red-100 text-red-800'],
                            default    => ['Required', 'bg-amber-100 text-amber-800'],
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="font-medium text-gray-900">Face verification</p>
                            <p class="text-xs text-gray-500 mt-0.5">Required before loan applications.</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $faceStatus[1] }}">{{ $faceStatus[0] }}</span>
                    </div>
                    <a href="{{ route('site.borrower.face-verification') }}" class="inline-flex mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">
                        {{ ($customer->face_verification_status ?? 'incomplete') === 'verified' ? 'View face verification' : 'Complete face verification →' }}
                    </a>
                </div>
            </div>

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                Save contact details
            </button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
