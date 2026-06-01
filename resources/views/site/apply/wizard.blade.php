<x-site.borrower-layout :title="brand_title(__('borrower.apply.title'))" active="applications">
    <div class="max-w-4xl mx-auto">

        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ brand_name() }} {{ __('borrower.apply.smart_application') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ __('borrower.apply.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('borrower.apply.subtitle') }}</p>
        </div>

        @if (! ($eligibility['can_apply'] ?? false))
            <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                <strong>{{ __('borrower.apply.requirements_incomplete') }}</strong> {{ __('borrower.apply.requirements_hint') }}
            </div>
        @endif

        @php
            $sectionsByKey = collect($profileSections ?? [])->keyBy('key');
            $profileComplete = collect(['personal', 'residence', 'kin', 'activity'])
                ->every(fn ($k) => (bool) ($sectionsByKey[$k]['complete'] ?? false));
        @endphp

        @if ($reservation ?? null)
            <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                {{ __('borrower.apply.asset_reservation', ['asset' => $reservation->asset?->title, 'amount' => number_format($reservation->deposit_amount)]) }}
            </div>
        @endif

        <div x-data="applyWizard({
                  products: {{ json_encode($products->map(fn($p)=>['id'=>$p->id,'code'=>$p->code,'name'=>$p->name,'rate'=>(float)$p->interest_rate,'min'=>(float)$p->min_amount,'max'=>(float)$p->max_amount,'tmin'=>(int)$p->tenure_min_months,'tmax'=>(int)$p->tenure_max_months,'desc'=>$p->description,'requires_guarantor'=>(bool)$p->requires_guarantor,'frequency'=>'weekly'])) }},
                  preselect: {{ $preselect ? (int)$preselect : 'null' }},
                  applicationFee: {{ (int) ($applicationFee ?? 0) }},
                  initialPlan: @js($stepPlan),
                  profileSections: @js($profileSections),
                  incomeVerification: @js($incomeVerification),
                  productQuestions: @js($productQuestions),
                  purposeLabels: @js(loan_purpose_options()),
                  readinessUrl: @js($readinessUrl),
                  i18n: @js([
                      'flexibleTerms' => __('borrower.apply.browse.flexible_terms'),
                      'monthsShort' => __('borrower.apply.browse.months_short'),
                      'months' => __('borrower.apply.quote.months'),
                      'back' => __('borrower.apply.back'),
                      'backProducts' => __('borrower.apply.back_products'),
                      'incomeDocument' => __('borrower.apply.income.income_document'),
                      'steps' => [
                          'quote' => __('borrower.apply.steps.quote'),
                          'personal' => __('borrower.apply.steps.personal'),
                          'residence' => __('borrower.apply.steps.residence'),
                          'kin' => __('borrower.apply.steps.kin'),
                          'activity' => __('borrower.apply.steps.activity'),
                          'income' => __('borrower.apply.steps.income'),
                          'product_questions' => __('borrower.apply.steps.product_questions'),
                      ],
                      'alerts' => [
                          'loadProduct' => __('borrower.apply.alerts.load_product'),
                          'selectPurpose' => __('borrower.apply.alerts.select_purpose'),
                          'selectGuarantor' => __('borrower.apply.alerts.select_guarantor'),
                          'acceptTerms' => __('borrower.apply.alerts.accept_terms'),
                          'drawSignature' => __('borrower.apply.alerts.draw_signature'),
                          'submitTitle' => __('borrower.apply.alerts.submit_title'),
                          'submitMessage' => __('borrower.apply.alerts.submit_message'),
                      ],
                      'review' => [
                          'documentOnFile' => __('borrower.apply.income.document_on_file'),
                          'uploadAtStep' => __('borrower.apply.income.upload_at_step'),
                      ],
                  ]),
              })"
             x-init="init()"
             x-cloak>

            {{-- Phase 1: Browse products --}}
            <div x-show="phase === 'browse'" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
                <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.browse.title') }}</h2>
                <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.browse.subtitle') }}</p>
                <div class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory -mx-1 px-1">
                    <template x-for="p in products" :key="p.id">
                        <button type="button" @click="openProduct(p)"
                                class="snap-start shrink-0 w-64 text-left rounded-xl border-2 border-gray-200 hover:border-amber-300 p-4 transition">
                            <span class="text-[10px] font-mono font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded" x-text="p.code"></span>
                            <div class="mt-2 font-semibold text-sm" x-text="p.name"></div>
                            <p class="text-[11px] text-gray-500 mt-1 line-clamp-2" x-text="p.desc || i18n.flexibleTerms"></p>
                            <div class="text-[11px] text-gray-600 mt-2">
                                <span x-text="formatTzs(p.min)+' – '+formatTzs(p.max)"></span>
                                · <span x-text="p.tmin+'–'+p.tmax+' '+i18n.monthsShort"></span>
                            </div>
                            <p class="mt-3 text-xs font-semibold text-amber-700">{{ __('borrower.apply.browse.view_details') }}</p>
                        </button>
                    </template>
                </div>
                <div class="mt-6 text-center">
                    <a href="{{ route('site.borrower.dashboard') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">{{ __('borrower.apply.back_dashboard') }}</a>
                </div>
            </div>

            {{-- Phase 2: Product details + readiness --}}
            <div x-show="phase === 'details'" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
                <x-site.loan-product-details />
            </div>

            {{-- Phase 3: Application wizard --}}
            <div x-show="phase === 'application'">
                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                        <p class="font-semibold mb-1">{{ __('borrower.apply.errors_fix') }}</p>
                        <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('site.borrower.apply.submit') }}" enctype="multipart/form-data" novalidate
                      @submit="onSubmit($event)">
                    @csrf
                    @if ($reservation ?? null)
                        <input type="hidden" name="asset_reservation_id" value="{{ $reservation->id }}">
                    @endif

            {{-- Always submit profile data for sections already on file --}}
            @if ($profileComplete || ($sectionsByKey['personal']['complete'] ?? false))
                <input type="hidden" name="first_name" value="{{ old('first_name', $customer->first_name) }}">
                <input type="hidden" name="last_name" value="{{ old('last_name', $customer->last_name) }}">
                <input type="hidden" name="date_of_birth" value="{{ old('date_of_birth', $customer->date_of_birth?->format('Y-m-d')) }}">
                <input type="hidden" name="gender" value="{{ old('gender', $customer->gender) }}">
                <input type="hidden" name="national_id" value="{{ old('national_id', $customer->national_id) }}">
            @endif
            @if ($profileComplete || ($sectionsByKey['residence']['complete'] ?? false))
                <input type="hidden" name="region" value="{{ old('region', $customer->region) }}">
                <input type="hidden" name="district" value="{{ old('district', $customer->district) }}">
                <input type="hidden" name="ward" value="{{ old('ward', $customer->ward) }}">
                <input type="hidden" name="street" value="{{ old('street', $customer->street ?? $customer->address) }}">
            @endif
            @if ($profileComplete || ($sectionsByKey['kin']['complete'] ?? false))
                <input type="hidden" name="nok_name" value="{{ old('nok_name', $customer->nok_name) }}">
                <input type="hidden" name="nok_relationship" value="{{ old('nok_relationship', $customer->nok_relationship) }}">
                <input type="hidden" name="nok_phone" value="{{ old('nok_phone', $customer->nok_phone) }}">
                <input type="hidden" name="nok_region" value="{{ old('nok_region', $customer->nok_region) }}">
                <input type="hidden" name="nok_district" value="{{ old('nok_district', $customer->nok_district) }}">
            @endif
            @if ($profileComplete || ($sectionsByKey['activity']['complete'] ?? false))
                <input type="hidden" name="activity_type" value="{{ old('activity_type', $customer->activity_type ?? $customer->employment_type) }}">
                <input type="hidden" name="income_range" value="{{ old('income_range', $customer->income_range) }}">
                @foreach ($customer->activity_details ?? [] as $detailKey => $detailValue)
                    <input type="hidden" name="activity_details[{{ $detailKey }}]" value="{{ $detailValue }}">
                @endforeach
            @endif

            <template x-if="current && !current.requires_guarantor">
                <input type="hidden" name="guarantor_mode" value="none">
            </template>

            {{-- Step progress --}}
            <ol class="flex items-center gap-1 mb-8 overflow-x-auto pb-2">
                <template x-for="(s, i) in steps" :key="s.key">
                    <li class="flex items-center gap-1 shrink-0">
                        <button type="button" @click="goto(i)"
                                :class="i === step ? 'bg-amber-500 text-gray-900 border-amber-500'
                                                   : (i < step ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                               : 'bg-white text-gray-500 border-gray-300')"
                                class="size-8 rounded-full grid place-items-center text-xs font-bold border-2 transition">
                            <template x-if="i < step"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg></template>
                            <template x-if="i >= step"><span x-text="i + 1"></span></template>
                        </button>
                        <span class="hidden sm:inline text-[11px] font-medium text-gray-600 mr-2" x-text="s.label"></span>
                        <span x-show="i < steps.length - 1" class="text-gray-300 hidden sm:inline">→</span>
                    </li>
                </template>
            </ol>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">

                    <input type="hidden" name="loan_product_id" :value="form.loan_product_id">

                {{-- Quote --}}
                <div x-show="currentStepKey === 'quote'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.quote.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.quote.subtitle') }}</p>
                    <template x-if="current">
                        <div class="space-y-5">
                            <div class="bg-gray-50 rounded-xl p-5">
                                <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">{{ __('borrower.apply.quote.loan_amount') }}</span><span class="font-bold" x-text="formatTzs(form.requested_amount)"></span></div>
                                <input type="range" :min="current.min" :max="current.max" step="50000" x-model.number="form.requested_amount" @input="updateQuote()" class="w-full accent-amber-500">
                                <input type="hidden" name="requested_amount" :value="form.requested_amount">
                                <div class="flex justify-between text-sm mb-2 mt-4"><span class="text-gray-600">{{ __('borrower.apply.quote.tenure') }}</span><span class="font-bold"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</span></div>
                                <input type="range" :min="current.tmin" :max="current.tmax" step="1" x-model.number="form.requested_tenure_months" @input="updateQuote()" class="w-full accent-amber-500">
                                <input type="hidden" name="requested_tenure_months" :value="form.requested_tenure_months">
                                <div class="flex justify-between text-sm mt-4"><span class="text-gray-600">{{ __('borrower.apply.quote.repayment_frequency') }}</span><span class="font-medium capitalize" x-text="current.frequency || 'monthly'"></span></div>
                            </div>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                <div class="rounded-xl ring-1 ring-gray-100 bg-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.monthly_installment') }}</p><p class="font-bold mt-1" x-text="formatTzs(quote.monthly)"></p></div>
                                <div class="rounded-xl ring-1 ring-gray-100 bg-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.weekly_installment') }}</p><p class="font-bold mt-1" x-text="formatTzs(quote.weekly)"></p></div>
                                <div class="rounded-xl ring-1 ring-gray-100 bg-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.interest_est') }}</p><p class="font-bold mt-1" x-text="formatTzs(quote.interest)"></p></div>
                                <div class="rounded-xl ring-1 ring-gray-100 bg-white p-4"><p class="text-[10px] uppercase text-gray-500">{{ __('borrower.apply.quote.total_repayment') }}</p><p class="font-bold mt-1" x-text="formatTzs(quote.total)"></p></div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.quote.purpose') }}</label>
                                <select name="purpose" x-model="form.purpose" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                    <option value="">{{ __('borrower.apply.quote.select_purpose') }}</option>
                                    @foreach ($loanPurposes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Profile verified --}}
                <div x-show="currentStepKey === 'profile_verify'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.profile_verify.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.profile_verify.subtitle') }}</p>
                    <ul class="grid sm:grid-cols-2 gap-3">
                        @foreach (['personal' => __('borrower.profile.personal'), 'activity' => __('borrower.profile.activity'), 'residence' => __('borrower.profile.residence'), 'kin' => __('borrower.profile.kin')] as $key => $label)
                            <li class="rounded-xl ring-1 ring-emerald-200 bg-emerald-50 px-4 py-3">
                                <p class="text-sm font-semibold text-emerald-900">✓ {{ __('borrower.apply.profile_verify.verified', ['section' => $label]) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Personal --}}
                @unless ($profileComplete || ($sectionsByKey['personal']['complete'] ?? false))
                <div x-show="currentStepKey === 'personal'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.personal.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-6">{{ __('borrower.apply.personal.subtitle') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.first_name') }}</label><input name="first_name" value="{{ old('first_name', $customer->first_name ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.last_name') }}</label><input name="last_name" value="{{ old('last_name', $customer->last_name ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.date_of_birth') }}</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer->date_of_birth?->format('Y-m-d')) }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.gender') }}</label>
                            <select name="gender" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                <option value="">{{ __('borrower.profile.select') }}</option>
                                @foreach (trans('borrower.profile.gender_options') as $v => $l)<option value="{{ $v }}" @selected(old('gender', $customer->gender ?? '') === $v)>{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.nida_number') }}</label><input name="national_id" value="{{ old('national_id', $customer->national_id ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono"></div>
                    </div>
                </div>
                @endunless

                {{-- Residence --}}
                @unless ($profileComplete || ($sectionsByKey['residence']['complete'] ?? false))
                <div x-show="currentStepKey === 'residence'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.residence.title') }}</h2>
                    <div class="grid sm:grid-cols-2 gap-4" x-data="tzAddress(@js(config('tanzania_locations')), @js(old('region', $customer->region ?? '')), @js(old('district', $customer->district ?? '')), @js(['selectRegion' => __('borrower.profile.select_region'), 'selectDistrict' => __('borrower.profile.select_district')]))" x-init="init()">
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.region') }}</label>
                            <select name="region" x-model="region" @change="onRegionChange()" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"><option value="" x-text="labels.selectRegion || 'Select region'"></option><template x-for="(districts, name) in locations" :key="name"><option :value="name" x-text="name"></option></template></select>
                        </div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.district') }}</label>
                            <select name="district" x-model="district" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"><option value="" x-text="labels.selectDistrict || 'Select district'"></option><template x-for="d in districtOptions" :key="d"><option :value="d" x-text="d"></option></template></select>
                        </div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.ward') }}</label><input name="ward" value="{{ old('ward', $customer->ward ?? '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.street') }}</label><input name="street" value="{{ old('street', $customer->street ?? $customer->address ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                    </div>
                </div>
                @endunless

                {{-- Next of kin --}}
                @unless ($profileComplete || ($sectionsByKey['kin']['complete'] ?? false))
                <div x-show="currentStepKey === 'kin'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.kin.title') }}</h2>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.full_name') }}</label><input name="nok_name" value="{{ old('nok_name', $customer->nok_name ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.relationship') }}</label><input name="nok_relationship" value="{{ old('nok_relationship', $customer->nok_relationship ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }}</label><input name="nok_phone" value="{{ old('nok_phone', $customer->nok_phone ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4 mt-4" x-data="tzAddress(@js(config('tanzania_locations')), @js(old('nok_region', $customer->nok_region ?? '')), @js(old('nok_district', $customer->nok_district ?? '')), @js(['selectRegion' => __('borrower.profile.select_region'), 'selectDistrict' => __('borrower.profile.select_district')]))" x-init="init()">
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.region') }}</label>
                            <select name="nok_region" x-model="region" @change="onRegionChange()" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"><option value="" x-text="labels.selectRegion || 'Select region'"></option><template x-for="(districts, name) in locations" :key="name"><option :value="name" x-text="name"></option></template></select>
                        </div>
                        <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.district') }}</label>
                            <select name="nok_district" x-model="district" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"><option value="" x-text="labels.selectDistrict || 'Select district'"></option><template x-for="d in districtOptions" :key="d"><option :value="d" x-text="d"></option></template></select>
                        </div>
                    </div>
                </div>
                @endunless

                {{-- Activity --}}
                @unless ($profileComplete || ($sectionsByKey['activity']['complete'] ?? false))
                <div x-show="currentStepKey === 'activity'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.profile.activity') }}</h2>
                    <x-site.activity-fields
                        :activity-type="old('activity_type', $customer->activity_type ?? $customer->employment_type)"
                        :activity-details="old('activity_details', $customer->activity_details ?? [])"
                        :income-range="old('income_range', $customer->income_range)"
                    />
                </div>
                @endunless

                {{-- Income --}}
                <div x-show="currentStepKey === 'income'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.income.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.income.subtitle') }}</p>
                    <template x-if="incomeVerification.has_document">
                        <div class="rounded-xl ring-1 ring-emerald-200 bg-emerald-50 px-4 py-3 mb-5">
                            <p class="text-sm font-semibold text-emerald-900">✓ {{ __('borrower.apply.income.document_on_file') }}</p>
                            <p class="text-xs text-emerald-700 mt-0.5" x-text="(incomeVerification.label || i18n.incomeDocument) + ' — ' + (incomeVerification.status || '{{ __('borrower.apply.income.pending_review') }}')"></p>
                        </div>
                    </template>
                    <template x-if="! incomeVerification.has_document">
                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="income_document_type" value="bank" x-model="form.income_type" class="text-amber-500"> {{ __('borrower.apply.income.bank_statement') }}</label>
                                <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="income_document_type" value="mobile_money" x-model="form.income_type" class="text-amber-500"> {{ __('borrower.apply.income.mobile_money') }}</label>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.income.upload_label') }}</label>
                                <input type="file" name="income_document" accept="image/*,application/pdf" class="w-full text-sm">
                            </div>
                            <p class="text-xs text-gray-500">{{ __('borrower.apply.income.status_after_upload') }} <span class="font-semibold text-amber-700">{{ __('borrower.apply.income.pending_review') }}</span></p>
                        </div>
                    </template>
                </div>

                {{-- Guarantor --}}
                <div x-show="currentStepKey === 'guarantor'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.guarantor') }}</h2>
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.apply.guarantor_required') }}</p>
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-3">
                            <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="guarantor_mode" value="internal" x-model="form.guarantor_mode" class="text-amber-500"> {{ __('borrower.apply.internal_guarantor') }}</label>
                            <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="guarantor_mode" value="external" x-model="form.guarantor_mode" class="text-amber-500"> {{ __('borrower.apply.external_guarantor') }}</label>
                        </div>
                        <div x-show="form.guarantor_mode === 'internal'">
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.membership_no') }}</label>
                            <input name="internal_member_no" placeholder="KPF-TZ-XXXXXX" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono">
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.apply.guarantor_fields.membership_hint') }}</p>
                        </div>
                        <div x-show="form.guarantor_mode === 'external'" class="grid sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.full_name') }}</label><input name="external_name" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }}</label><input name="external_phone" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }} {{ __('borrower.profile.optional') }}</label><input name="external_email" type="email" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></div>
                            <div class="sm:col-span-2"><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.share_via') }}</label>
                                <select name="external_channel" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="sms">SMS</option>
                                    <option value="email">{{ __('borrower.profile.fields.email') }}</option>
                                </select>
                            </div>
                        </div>
                        <p class="text-xs text-amber-700 font-medium">{{ __('borrower.apply.guarantor_fields.status_waiting') }}</p>
                    </div>
                </div>

                {{-- Product-specific questions --}}
                <div x-show="currentStepKey === 'product_questions'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.product_questions.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.product_questions.subtitle') }}</p>
                    @foreach ($productQuestions as $code => $block)
                        <div x-show="current && current.code === @js($code)" class="rounded-xl border border-gray-200 p-5">
                            <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $block['title'] ?? __('borrower.apply.product_questions.additional') }}</h3>
                            <div class="grid sm:grid-cols-2 gap-4">
                                @foreach ($block['fields'] as $field)
                                    <div class="{{ ($field['type'] ?? 'text') === 'textarea' ? 'sm:col-span-2' : '' }}">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ $field['label'] }}</label>
                                        @if (($field['type'] ?? 'text') === 'select')
                                            <select name="product_question[{{ $field['key'] }}]" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                                <option value="">{{ __('borrower.profile.select') }}</option>
                                                @foreach ($field['options'] ?? [] as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @elseif (($field['type'] ?? 'text') === 'textarea')
                                            <textarea name="product_question[{{ $field['key'] }}]" rows="3" placeholder="{{ $field['placeholder'] ?? '' }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"></textarea>
                                        @else
                                            <input type="text" name="product_question[{{ $field['key'] }}]" placeholder="{{ $field['placeholder'] ?? '' }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Review --}}
                <div x-show="currentStepKey === 'review'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.review_step.title') }}</h2>
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.apply.review_step.subtitle') }}</p>
                    <div class="rounded-xl border border-gray-200 divide-y divide-gray-200 mb-5 text-sm">
                        <div class="px-4 py-3 flex justify-between gap-3"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.product') }}</span><span class="font-medium" x-text="current ? current.name : '—'"></span></div><button type="button" @click="backToBrowse()" class="text-xs text-amber-700 shrink-0">{{ __('borrower.apply.change') }}</button></div>
                        <div class="px-4 py-3 flex justify-between gap-3"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_step.amount_tenure') }}</span><span class="font-medium"><span x-text="formatTzs(form.requested_amount)"></span> · <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.browse.months_short') }}</span></div><button type="button" @click="gotoKey('quote')" class="text-xs text-amber-700 shrink-0">{{ __('borrower.apply.edit') }}</button></div>
                        <div class="px-4 py-3"><span class="text-gray-500 block">{{ __('borrower.apply.review_step.purpose') }}</span><span class="font-medium" x-text="purposeLabels[form.purpose] || form.purpose || '—'"></span></div>
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="review.personal"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_personal') }}</span><span class="font-medium" x-text="review.personal"></span></div><button type="button" @click="gotoKey('personal')" class="text-xs text-amber-700 shrink-0" x-show="hasStep('personal')">{{ __('borrower.apply.edit') }}</button></div>
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="review.residence"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_residence') }}</span><span class="font-medium" x-text="review.residence"></span></div><button type="button" @click="gotoKey('residence')" class="text-xs text-amber-700 shrink-0" x-show="hasStep('residence')">{{ __('borrower.apply.edit') }}</button></div>
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="review.nok"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_kin') }}</span><span class="font-medium" x-text="review.nok"></span></div><button type="button" @click="gotoKey('kin')" class="text-xs text-amber-700 shrink-0" x-show="hasStep('kin')">{{ __('borrower.apply.edit') }}</button></div>
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="review.activity"><div><span class="text-gray-500 block">{{ __('borrower.apply.review_activity') }}</span><span class="font-medium" x-text="review.activity"></span></div><button type="button" @click="gotoKey('activity')" class="text-xs text-amber-700 shrink-0" x-show="hasStep('activity')">{{ __('borrower.apply.edit') }}</button></div>
                        <div class="px-4 py-3 flex justify-between gap-3" x-show="hasStep('guarantor')"><div><span class="text-gray-500 block">{{ __('borrower.apply.guarantor') }}</span><span class="font-medium" x-text="review.guarantor || '—'"></span></div><button type="button" @click="gotoKey('guarantor')" class="text-xs text-amber-700 shrink-0">{{ __('borrower.apply.edit') }}</button></div>
                        <div class="px-4 py-3" x-show="hasStep('income')"><span class="text-gray-500 block">{{ __('borrower.apply.income.review_income') }}</span><span class="font-medium" x-text="incomeVerification.has_document ? i18n.review.documentOnFile : i18n.review.uploadAtStep"></span></div>
                    </div>
                </div>

                {{-- Signature --}}
                <div x-show="currentStepKey === 'signature'" class="p-6 sm:p-8">
                    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.signature_title') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.signature_subtitle') }}</p>
                    <label class="flex items-start gap-3 text-sm text-gray-700 mb-5">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        <span>{{ __('borrower.apply.signature_consent', ['brand' => brand_name()]) }}</span>
                    </label>
                    <x-site.signature-pad :default-name="trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))" />
                </div>

                <div class="px-6 sm:px-8 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl flex items-center justify-between">
                    <button type="button" @click="step > 0 ? prev() : backToDetails()" class="text-sm font-medium text-gray-600 hover:text-gray-900" x-text="step > 0 ? i18n.back : i18n.backProducts"></button>
                    <div class="ml-auto flex items-center gap-3">
                        <a href="{{ route('site.borrower.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('borrower.apply.cancel') }}</a>
                        <button type="button" @click="next()" x-show="currentStepKey !== 'signature'" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.apply.continue') }}</button>
                        <button type="submit" x-show="currentStepKey === 'signature'" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.apply.submit') }}</button>
                    </div>
                </div>
            </div>
                </form>
            </div>
        </div>
    </div>

    @stack('scripts')
    <script>
        function tzAddress(locations, initialRegion, initialDistrict, labels) {
            return {
                locations,
                labels: labels || {},
                region: initialRegion || '',
                district: initialDistrict || '',
                districtOptions: [],
                init() { this.refreshDistricts(); },
                onRegionChange() { this.district = ''; this.refreshDistricts(); },
                refreshDistricts() {
                    this.districtOptions = this.region && this.locations[this.region] ? this.locations[this.region] : [];
                },
            };
        }
        function applyWizard(config) {
            return {
                products: config.products,
                applicationFee: config.applicationFee,
                purposeLabels: config.purposeLabels,
                productQuestions: config.productQuestions,
                profileSections: config.profileSections,
                incomeVerification: config.incomeVerification,
                readinessUrl: config.readinessUrl,
                i18n: config.i18n,
                phase: 'browse',
                readiness: null,
                readinessLoading: false,
                steps: [],
                step: 0,
                current: null,
                form: {
                    loan_product_id: null,
                    requested_amount: 0,
                    requested_tenure_months: 0,
                    purpose: '',
                    guarantor_mode: 'internal',
                    income_type: 'bank',
                },
                quote: { monthly: 0, weekly: 0, interest: 0, total: 0, fees: 0 },
                review: { personal: '', residence: '', nok: '', activity: '', guarantor: '' },

                get currentStepKey() {
                    return this.steps[this.step]?.key ?? '';
                },

                init() {
                    if (config.preselect) {
                        const p = this.products.find(x => x.id == config.preselect);
                        if (p) this.openProduct(p);
                    }
                },

                openProduct(p) {
                    this.current = p;
                    this.form.loan_product_id = p.id;
                    this.phase = 'details';
                    this.loadReadiness(p.id);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                backToBrowse() {
                    this.phase = 'browse';
                    this.readiness = null;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                backToDetails() {
                    this.phase = 'details';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                completeMissingRequirements() {
                    if (! this.readiness) return;
                    if (this.readiness.missing_action_url) {
                        window.location.href = this.readiness.missing_action_url;
                        return;
                    }
                    this.startApplication();
                },

                loadReadiness(productId) {
                    this.readinessLoading = true;
                    this.readiness = null;
                    const url = this.readinessUrl.replace('__ID__', encodeURIComponent(productId));
                    fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    })
                        .then(res => res.ok ? res.json() : Promise.reject(res))
                        .then(data => {
                            this.readiness = data;
                            if (this.phase === 'application' && this.current) {
                                this.rebuildSteps();
                            }
                        })
                        .catch(() => { this.readiness = null; alert(this.i18n.alerts.loadProduct); })
                        .finally(() => { this.readinessLoading = false; });
                },

                startApplication() {
                    if (! this.current) return;
                    this.selectProduct(this.current, true);
                    this.phase = 'application';
                    this.step = 0;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                rebuildSteps() {
                    if (this.readiness?.step_plan?.length) {
                        this.steps = this.readiness.step_plan.map(s => ({ key: s.key, label: s.label }));
                        if (this.step >= this.steps.length) this.step = this.steps.length - 1;
                        return;
                    }

                    const profileKeys = ['personal', 'residence', 'kin', 'activity'];
                    const sections = Object.fromEntries(this.profileSections.map(s => [s.key, s]));
                    const profileComplete = profileKeys.every(k => sections[k]?.complete);
                    const stepLabels = this.i18n.steps;
                    const steps = [{ key: 'quote', label: stepLabels.quote }];
                    if (! profileComplete) {
                        profileKeys.forEach(k => {
                            if (! sections[k]?.complete) {
                                steps.push({ key: k, label: stepLabels[k] || k });
                            }
                        });
                    }
                    if (! this.incomeVerification.can_skip) {
                        steps.push({ key: 'income', label: stepLabels.income });
                    }
                    if (this.current?.requires_guarantor) {
                        steps.push({ key: 'guarantor', label: @js(__('borrower.apply.guarantor')) });
                    }
                    if (this.current?.code && this.productQuestions[this.current.code]) {
                        steps.push({ key: 'product_questions', label: stepLabels.product_questions });
                    }
                    steps.push({ key: 'review', label: @js(__('borrower.apply.review')) });
                    steps.push({ key: 'signature', label: @js(__('borrower.apply.sign')) });
                    this.steps = steps;
                    if (this.step >= this.steps.length) this.step = this.steps.length - 1;
                },

                selectProduct(p, rebuild = true) {
                    this.current = p;
                    this.form.loan_product_id = p.id;
                    if (! this.form.requested_amount || this.form.requested_amount < p.min) this.form.requested_amount = p.min;
                    if (! this.form.requested_tenure_months || this.form.requested_tenure_months < p.tmin) this.form.requested_tenure_months = p.tmin;
                    if (! p.requires_guarantor) this.form.guarantor_mode = 'none';
                    else if (this.form.guarantor_mode === 'none') this.form.guarantor_mode = 'internal';
                    this.updateQuote();
                    if (rebuild) this.rebuildSteps();
                },

                estimateEmi(principal, rate, months) {
                    if (principal <= 0 || months <= 0) return 0;
                    if (rate <= 0) return Math.round(principal / months);
                    const pow = Math.pow(1 + rate, months);
                    return Math.round(principal * rate * pow / (pow - 1));
                },

                updateQuote() {
                    if (! this.current) return;
                    const emi = this.estimateEmi(this.form.requested_amount, this.current.rate, this.form.requested_tenure_months);
                    const interest = Math.max(0, (emi * this.form.requested_tenure_months) - this.form.requested_amount);
                    this.quote = {
                        monthly: emi,
                        weekly: Math.round(emi / 4.33),
                        interest,
                        fees: this.applicationFee,
                        total: (emi * this.form.requested_tenure_months) + this.applicationFee,
                    };
                },

                hasStep(key) {
                    return this.steps.some(s => s.key === key);
                },

                gotoKey(key) {
                    const i = this.steps.findIndex(s => s.key === key);
                    if (i >= 0 && i <= this.step) this.step = i;
                },

                refreshReview(formEl) {
                    const fd = new FormData(formEl);
                    const g = (n) => fd.get(n) || '';
                    this.review.personal = [g('first_name'), g('last_name'), g('national_id')].filter(Boolean).join(' · ');
                    this.review.residence = [g('street'), g('ward'), g('district'), g('region')].filter(Boolean).join(', ');
                    this.review.nok = [g('nok_name'), g('nok_relationship'), g('nok_phone')].filter(Boolean).join(' · ');
                    this.review.activity = [g('activity_type'), g('income_range')].filter(Boolean).join(' · ');
                    if (this.form.guarantor_mode === 'internal') this.review.guarantor = g('internal_member_no');
                    if (this.form.guarantor_mode === 'external') this.review.guarantor = [g('external_name'), g('external_channel')].filter(Boolean).join(' via ');
                },

                validateStep() {
                    if (this.currentStepKey === 'quote' && ! this.form.purpose) {
                        alert(this.i18n.alerts.selectPurpose);
                        return false;
                    }
                    if (this.currentStepKey === 'guarantor' && this.current?.requires_guarantor) {
                        if (! this.form.guarantor_mode || this.form.guarantor_mode === 'none') {
                            alert(this.i18n.alerts.selectGuarantor);
                            return false;
                        }
                    }
                    return true;
                },

                next() {
                    if (! this.validateStep()) return;
                    const nextKey = this.steps[this.step + 1]?.key;
                    if (nextKey === 'review') {
                        this.refreshReview(this.$root);
                    }
                    if (this.step < this.steps.length - 1) {
                        this.step++;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },

                prev() {
                    if (this.step > 0) {
                        this.step--;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },

                goto(i) {
                    if (i <= this.step) this.step = i;
                },

                onSubmit(e) {
                    const consent = e.target.elements['consent'];
                    const sig = e.target.elements['signature_data'];
                    if (consent && ! consent.checked) {
                        e.preventDefault();
                        alert(this.i18n.alerts.acceptTerms);
                        return;
                    }
                    if (sig && ! sig.value) {
                        e.preventDefault();
                        alert(this.i18n.alerts.drawSignature);
                        return;
                    }
                    e.preventDefault();
                    window.confirmForm(e.target, {
                        title: this.i18n.alerts.submitTitle,
                        message: this.i18n.alerts.submitMessage,
                        confirmLabel: @js(__('borrower.apply.submit')),
                        confirmClass: 'bg-gray-900 hover:bg-gray-800 text-white',
                    });
                },

                formatTzs(v) {
                    return 'TZS ' + new Intl.NumberFormat('en-US').format(Math.round(v || 0));
                },
            };
        }
    </script>
</x-site.borrower-layout>
