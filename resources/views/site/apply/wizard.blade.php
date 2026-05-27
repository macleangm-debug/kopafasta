<x-site.layout title="Apply for a loan — Kopafasta">
    <section class="max-w-4xl mx-auto px-4 py-10 sm:py-14">

        <div class="text-center mb-8">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-2">Loan application</p>
            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">Let's get you funded</h1>
            <p class="mt-2 text-gray-600">Five quick steps. About 5 minutes. Final terms after review.</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                <p class="font-semibold mb-1">Please fix the following:</p>
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('site.apply.submit') }}" novalidate
              x-data="applyWizard({{ json_encode($products->map(fn($p)=>['id'=>$p->id,'code'=>$p->code,'name'=>$p->name,'rate'=>(float)$p->interest_rate,'min'=>(float)$p->min_amount,'max'=>(float)$p->max_amount,'tmin'=>(int)$p->tenure_min_months,'tmax'=>(int)$p->tenure_max_months,'desc'=>$p->description])) }}, {{ $preselect ? (int)$preselect : 'null' }}, {{ (int) $registrationFee }}, {{ (int) ($applicationFee ?? 0) }})"
              x-init="init()"
              @submit="onSubmit($event)"
              x-cloak>
            @csrf

            {{-- STEPPER --}}
            <ol class="flex items-center justify-between mb-8 gap-2">
                <template x-for="(label, i) in labels" :key="i">
                    <li class="flex-1 flex items-center gap-3">
                        <button type="button" @click="goto(i)"
                                :class="i === step ? 'bg-amber-500 text-gray-900 border-amber-500'
                                                   : (i < step ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                               : 'bg-white text-gray-500 border-gray-300')"
                                class="size-9 rounded-full grid place-items-center text-sm font-bold border-2 transition shrink-0">
                            <template x-if="i < step">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg>
                            </template>
                            <template x-if="i >= step">
                                <span x-text="i + 1"></span>
                            </template>
                        </button>
                        <div class="hidden sm:block min-w-0">
                            <div class="text-[10px] uppercase tracking-widest text-gray-400">Step <span x-text="i+1"></span></div>
                            <div class="text-xs font-medium text-gray-700 truncate" x-text="label"></div>
                        </div>
                        <template x-if="i < labels.length - 1">
                            <div class="hidden sm:block flex-1 h-px bg-gray-200"></div>
                        </template>
                    </li>
                </template>
            </ol>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">

                {{-- ===== STEP 1: Registration fee ===== --}}
                <div x-show="step === 0" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">Pay the registration fee</h2>
                    <p class="text-sm text-gray-600 mb-6">A one-time fee to cover credit checks and processing. Refundable if your loan is declined.</p>

                    <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200 p-6 mb-6 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-amber-700 font-semibold">Amount due</p>
                            <p class="mt-1 text-3xl font-extrabold text-gray-900" x-text="formatTzs({{ (int) $registrationFee }})"></p>
                            <p class="mt-1 text-xs text-amber-800">One-time · non-recurring</p>
                        </div>
                        <svg class="w-12 h-12 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zm3 9h3"/></svg>
                    </div>

                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-2">Choose how you paid</p>
                    <div class="grid sm:grid-cols-2 gap-3 mb-6">
                        @foreach ($payChannels as $ch)
                            <label class="cursor-pointer">
                                <input type="radio" name="registration_fee_channel" value="{{ $ch['name'] }}" x-model="form.registration_fee_channel" class="sr-only peer" required>
                                <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-4 transition">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-sm text-gray-900">{{ $ch['name'] }}</span>
                                        <span class="text-[10px] font-mono text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">{{ $ch['till'] }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-600">{{ $ch['note'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <label class="block text-xs font-medium text-gray-600 mb-1">Transaction reference / SMS code</label>
                    <input name="registration_fee_reference" x-model="form.registration_fee_reference" required
                           placeholder="e.g. SHJ5K8GH22"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm uppercase font-mono">
                    <p class="mt-2 text-xs text-gray-500">Paste the confirmation code from your M-Pesa/Tigo/Airtel SMS, or the bank reference number.</p>
                </div>

                {{-- ===== STEP 2: Product & amount ===== --}}
                <div x-show="step === 1" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">Pick your loan</h2>
                    <p class="text-sm text-gray-600 mb-6">Choose a product, then dial in your amount and tenure.</p>

                    <div class="grid sm:grid-cols-2 gap-3 mb-6 max-h-72 overflow-y-auto pr-1">
                        <template x-for="p in products" :key="p.id">
                            <label :class="form.loan_product_id == p.id ? 'border-amber-500 ring-2 ring-amber-200 bg-amber-50' : 'border-gray-200 hover:border-amber-300'"
                                   class="block rounded-xl border-2 p-4 cursor-pointer transition">
                                <input type="radio" name="loan_product_id" :value="p.id" x-model="form.loan_product_id" @change="onProduct()" class="sr-only">
                                <div class="flex items-start justify-between">
                                    <span class="text-[10px] font-mono font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded" x-text="p.code"></span>
                                    <span class="text-[11px] text-gray-500">from <b x-text="(p.rate*100).toFixed(1)+'%'"></b>/mo</span>
                                </div>
                                <div class="mt-1 font-semibold text-sm" x-text="p.name"></div>
                                <div class="text-[11px] text-gray-500 mt-1" x-text="formatTzs(p.min)+' – '+formatTzs(p.max)"></div>
                            </label>
                        </template>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-5 mb-5" x-show="current">
                        <div class="flex items-center justify-between text-sm mb-2">
                            <span class="text-gray-600">Loan amount</span>
                            <span class="font-bold text-gray-900" x-text="formatTzs(form.requested_amount)"></span>
                        </div>
                        <input type="range" :min="current.min" :max="current.max" step="50000" x-model.number="form.requested_amount" class="w-full accent-amber-500">
                        <input type="hidden" name="requested_amount" :value="form.requested_amount">
                        <div class="flex justify-between text-[11px] text-gray-500 mb-4">
                            <span x-text="formatTzs(current.min)"></span><span x-text="formatTzs(current.max)"></span>
                        </div>

                        <div class="flex items-center justify-between text-sm mb-2">
                            <span class="text-gray-600">Tenure</span>
                            <span class="font-bold text-gray-900"><span x-text="form.requested_tenure_months"></span> months</span>
                        </div>
                        <input type="range" :min="current.tmin" :max="current.tmax" step="1" x-model.number="form.requested_tenure_months" class="w-full accent-amber-500">
                        <input type="hidden" name="requested_tenure_months" :value="form.requested_tenure_months">

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <div class="text-[10px] uppercase tracking-wider text-gray-500">Monthly payment</div>
                                <div class="text-lg font-bold" x-text="formatTzs(monthly)"></div>
                            </div>
                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <div class="text-[10px] uppercase tracking-wider text-gray-500">Total repayment</div>
                                <div class="text-lg font-bold" x-text="formatTzs(total)"></div>
                            </div>
                        </div>
                    </div>

                    <label class="block text-xs font-medium text-gray-600 mb-1">What will you use this loan for?</label>
                    <textarea name="purpose" x-model="form.purpose" rows="2" required
                              placeholder="e.g. Stock for my shop, replace generator, school fees…"
                              class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm"></textarea>
                </div>

                {{-- ===== STEP 3: Personal ===== --}}
                <div x-show="step === 2" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">About you</h2>
                    <p class="text-sm text-gray-600 mb-6">We'll verify these against NIDA. Make sure they match your ID.</p>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">First name</label>
                            <input name="first_name" value="{{ old('first_name', $customer->first_name ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Last name</label>
                            <input name="last_name" value="{{ old('last_name', $customer->last_name ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date of birth</label>
                            <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer && $customer->date_of_birth ? $customer->date_of_birth->format('Y-m-d') : '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">National ID (NIDA)</label>
                            <input name="national_id" value="{{ old('national_id', $customer->national_id ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Address</label>
                            <input name="address" value="{{ old('address', $customer->address ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                </div>

                {{-- ===== STEP 4: Employment / income ===== --}}
                <div x-show="step === 3" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">Your income</h2>
                    <p class="text-sm text-gray-600 mb-6">Tells us your repayment capacity. Honest answers = faster approval.</p>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Employment type</label>
                            <select name="employment_type" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm">
                                @foreach (['salaried'=>'Salaried (employed)','self_employed'=>'Self-employed','business_owner'=>'Business owner','farmer'=>'Farmer','student'=>'Student','other'=>'Other'] as $v=>$l)
                                    <option value="{{ $v }}" @selected(old('employment_type', $customer->employment_type ?? '') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Business name (if any)</label>
                            <input name="business_name" value="{{ old('business_name', $customer->business_name ?? '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Monthly income (TZS)</label>
                            <input type="number" name="monthly_income" min="0" step="1000" value="{{ old('monthly_income', $customer->monthly_income ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 focus:border-amber-500 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                </div>

                {{-- ===== STEP 5: Review & consent ===== --}}
                <div x-show="step === 4" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">Review and submit</h2>
                    <p class="text-sm text-gray-600 mb-6">Check everything, then confirm.</p>

                    <div class="rounded-xl border border-gray-200 divide-y divide-gray-200 mb-5">
                        <div class="px-4 py-3 grid grid-cols-3 gap-3 text-sm">
                            <span class="text-gray-500">Registration fee</span>
                            <span class="col-span-2 font-medium">
                                <span x-text="formatTzs(registrationFee)"></span>
                                <span class="text-xs text-gray-500">· <span x-text="form.registration_fee_channel || '—'"></span> · ref <span class="font-mono" x-text="form.registration_fee_reference || '—'"></span></span>
                            </span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-3 gap-3 text-sm">
                            <span class="text-gray-500">Application fee</span>
                            <span class="col-span-2 font-medium">
                                <span x-text="formatTzs(applicationFee)"></span>
                                <span class="text-xs text-gray-500">· billed on disbursement</span>
                            </span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-3 gap-3 text-sm">
                            <span class="text-gray-500">Product</span>
                            <span class="col-span-2 font-medium" x-text="current ? current.name + ' (' + current.code + ')' : '—'"></span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-3 gap-3 text-sm">
                            <span class="text-gray-500">Amount</span>
                            <span class="col-span-2 font-medium" x-text="formatTzs(form.requested_amount)"></span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-3 gap-3 text-sm">
                            <span class="text-gray-500">Tenure</span>
                            <span class="col-span-2 font-medium"><span x-text="form.requested_tenure_months"></span> months</span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-3 gap-3 text-sm">
                            <span class="text-gray-500">Est. monthly</span>
                            <span class="col-span-2 font-medium" x-text="formatTzs(monthly)"></span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-3 gap-3 text-sm">
                            <span class="text-gray-500">Est. total</span>
                            <span class="col-span-2 font-medium" x-text="formatTzs(total)"></span>
                        </div>
                    </div>

                    <label class="flex items-start gap-3 text-sm text-gray-700">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        <span>I confirm the information above is correct, and I authorise Kopafasta to verify my identity and credit history, and to contact me about this application.</span>
                    </label>
                </div>

                {{-- ===== FOOTER NAV ===== --}}
                <div class="px-6 sm:px-8 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl flex items-center justify-between">
                    <button type="button" @click="prev()" x-show="step > 0"
                            class="text-sm font-medium text-gray-600 hover:text-gray-900 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 10H4m4 4-4-4 4-4"/></svg>
                        Back
                    </button>
                    <div class="ml-auto flex items-center gap-3">
                        <a href="{{ route('site.home') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <button type="button" @click="next()" x-show="step < labels.length - 1"
                                class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full transition">
                            Continue
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                        </button>
                        <button type="submit" x-show="step === labels.length - 1"
                                class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full transition">
                            Submit application
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 10l3 3 7-7"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <script>
        function applyWizard(products, preselect, registrationFee, applicationFee) {
            return {
                products,
                registrationFee,
                applicationFee,
                step: 0,
                labels: ['Fee', 'Loan', 'Personal', 'Income', 'Review'],
                form: {
                    registration_fee_channel: '',
                    registration_fee_reference: '',
                    loan_product_id: preselect ?? (products[0]?.id ?? null),
                    requested_amount: 0,
                    requested_tenure_months: 0,
                    purpose: '',
                },
                current: null,
                init() {
                    this.onProduct();
                },
                onProduct() {
                    this.current = this.products.find(p => p.id == this.form.loan_product_id) || this.products[0];
                    if (!this.current) return;
                    if (!this.form.requested_amount || this.form.requested_amount < this.current.min || this.form.requested_amount > this.current.max) {
                        this.form.requested_amount = this.current.min;
                    }
                    if (!this.form.requested_tenure_months || this.form.requested_tenure_months < this.current.tmin || this.form.requested_tenure_months > this.current.tmax) {
                        this.form.requested_tenure_months = this.current.tmin;
                    }
                },
                next() {
                    if (this.step === 0) {
                        if (!this.form.registration_fee_channel) { alert('Choose how you paid the registration fee.'); return; }
                        if (!this.form.registration_fee_reference || this.form.registration_fee_reference.length < 4) { alert('Enter the transaction reference.'); return; }
                    }
                    if (this.step < this.labels.length - 1) { this.step++; window.scrollTo({top: 0, behavior: 'smooth'}); }
                },
                prev() { if (this.step > 0) { this.step--; window.scrollTo({top: 0, behavior: 'smooth'}); } },
                goto(i) { this.step = i; window.scrollTo({top: 0, behavior: 'smooth'}); },
                onSubmit(e) {
                    // Final-step guards. Server still does the authoritative validation.
                    const f = e.target;
                    const need = ['first_name','last_name','national_id','address','employment_type','monthly_income'];
                    for (const n of need) {
                        const el = f.elements[n];
                        if (el && !String(el.value || '').trim()) {
                            e.preventDefault();
                            alert('Please complete: ' + n.replace('_',' '));
                            return;
                        }
                    }
                    const consent = f.elements['consent'];
                    if (consent && !consent.checked) {
                        e.preventDefault();
                        alert('Please accept the consent to submit.');
                        return;
                    }
                    // Allow native submit through.
                },
                get monthly() {
                    if (!this.current) return 0;
                    const r = this.current.rate || 0;
                    const n = this.form.requested_tenure_months || 1;
                    return Math.round((this.form.requested_amount / n) + (this.form.requested_amount * r));
                },
                get total() { return this.monthly * (this.form.requested_tenure_months || 1); },
                formatTzs(v) { return 'TZS ' + new Intl.NumberFormat('en-US').format(Math.round(v || 0)); },
            };
        }
    </script>
</x-site.layout>
