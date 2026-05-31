<x-site.borrower-layout title="Apply for a loan — Kopafasta" active="applications">
    <div class="max-w-4xl mx-auto">

        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">Loan application</p>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">Let's get you funded</h1>
            <p class="mt-1 text-sm text-gray-500">Four quick steps inside your account. Final terms after review.</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                <p class="font-semibold mb-1">Please fix the following:</p>
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('site.borrower.apply.submit') }}" novalidate
              x-data="applyWizard({{ json_encode($products->map(fn($p)=>['id'=>$p->id,'code'=>$p->code,'name'=>$p->name,'rate'=>(float)$p->interest_rate,'min'=>(float)$p->min_amount,'max'=>(float)$p->max_amount,'tmin'=>(int)$p->tenure_min_months,'tmax'=>(int)$p->tenure_max_months,'desc'=>$p->description])) }}, {{ $preselect ? (int)$preselect : 'null' }}, {{ (int) ($applicationFee ?? 0) }})"
              x-init="init()"
              @submit="onSubmit($event)"
              x-cloak>
            @csrf

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
                    </li>
                </template>
            </ol>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">

                {{-- STEP 1: Product --}}
                <div x-show="step === 0" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">Choose a loan product</h2>
                    <p class="text-sm text-gray-600 mb-6">Select the product that fits your need, then set amount and tenure.</p>

                    <div class="grid sm:grid-cols-2 gap-3 mb-6 max-h-96 overflow-y-auto pr-1">
                        <template x-for="p in products" :key="p.id">
                            <label :class="form.loan_product_id == p.id ? 'border-amber-500 ring-2 ring-amber-200 bg-amber-50' : 'border-gray-200 hover:border-amber-300'"
                                   class="block rounded-xl border-2 p-4 cursor-pointer transition">
                                <input type="radio" name="loan_product_id" :value="p.id" x-model="form.loan_product_id" @change="onProduct()" class="sr-only">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-[10px] font-mono font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded" x-text="p.code"></span>
                                    <span class="text-[11px] text-gray-500">from <b x-text="(p.rate*100).toFixed(1)+'%'"></b>/mo</span>
                                </div>
                                <div class="mt-1 font-semibold text-sm" x-text="p.name"></div>
                                <p class="text-[11px] text-gray-500 mt-1" x-text="p.desc || 'Flexible terms from configuration.'"></p>
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

                        <div class="flex items-center justify-between text-sm mb-2 mt-4">
                            <span class="text-gray-600">Tenure</span>
                            <span class="font-bold text-gray-900"><span x-text="form.requested_tenure_months"></span> months</span>
                        </div>
                        <input type="range" :min="current.tmin" :max="current.tmax" step="1" x-model.number="form.requested_tenure_months" class="w-full accent-amber-500">
                        <input type="hidden" name="requested_tenure_months" :value="form.requested_tenure_months">

                        <label class="block text-xs font-medium text-gray-600 mb-1 mt-4">Purpose</label>
                        <textarea name="purpose" x-model="form.purpose" rows="2" required
                                  placeholder="e.g. Stock for my shop, school fees…"
                                  class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm"></textarea>
                    </div>
                </div>

                {{-- STEP 2: Personal --}}
                <div x-show="step === 1" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">About you</h2>
                    <p class="text-sm text-gray-600 mb-6">Details must match your NIDA ID.</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">First name</label>
                            <input name="first_name" value="{{ old('first_name', $customer->first_name ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Last name</label>
                            <input name="last_name" value="{{ old('last_name', $customer->last_name ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date of birth</label>
                            <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer && $customer->date_of_birth ? $customer->date_of_birth->format('Y-m-d') : '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">National ID (NIDA)</label>
                            <input name="national_id" value="{{ old('national_id', $customer->national_id ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Address</label>
                            <input name="address" value="{{ old('address', $customer->address ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                </div>

                {{-- STEP 3: Income --}}
                <div x-show="step === 2" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">Your income</h2>
                    <div class="grid sm:grid-cols-2 gap-4 mt-6">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Employment type</label>
                            <select name="employment_type" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                                @foreach (['salaried'=>'Salaried','self_employed'=>'Self-employed','business_owner'=>'Business owner','farmer'=>'Farmer','student'=>'Student','other'=>'Other'] as $v=>$l)
                                    <option value="{{ $v }}" @selected(old('employment_type', $customer->employment_type ?? '') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Business name (if any)</label>
                            <input name="business_name" value="{{ old('business_name', $customer->business_name ?? '') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Monthly income (TZS)</label>
                            <input type="number" name="monthly_income" min="0" step="1000" value="{{ old('monthly_income', $customer->monthly_income ?? '') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                </div>

                {{-- STEP 4: Review --}}
                <div x-show="step === 3" class="p-6 sm:p-8" x-transition.opacity>
                    <h2 class="text-xl font-semibold mb-1">Review and submit</h2>
                    <div class="rounded-xl border border-gray-200 divide-y divide-gray-200 mb-5 mt-6 text-sm">
                        <div class="px-4 py-3 grid grid-cols-3 gap-3">
                            <span class="text-gray-500">Product</span>
                            <span class="col-span-2 font-medium" x-text="current ? current.name : '—'"></span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-3 gap-3">
                            <span class="text-gray-500">Amount</span>
                            <span class="col-span-2 font-medium" x-text="formatTzs(form.requested_amount)"></span>
                        </div>
                        <div class="px-4 py-3 grid grid-cols-3 gap-3">
                            <span class="text-gray-500">Tenure</span>
                            <span class="col-span-2 font-medium"><span x-text="form.requested_tenure_months"></span> months</span>
                        </div>
                        @if ((int) ($applicationFee ?? 0) > 0)
                        <div class="px-4 py-3 grid grid-cols-3 gap-3">
                            <span class="text-gray-500">Application fee</span>
                            <span class="col-span-2 font-medium">{{ number_format((int) $applicationFee) }} TZS · on disbursement</span>
                        </div>
                        @endif
                    </div>
                    <label class="flex items-start gap-3 text-sm text-gray-700">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        <span>I confirm the information is correct and authorise Kopafasta to verify my identity and credit history.</span>
                    </label>
                </div>

                <div class="px-6 sm:px-8 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl flex items-center justify-between">
                    <button type="button" @click="prev()" x-show="step > 0" class="text-sm font-medium text-gray-600 hover:text-gray-900">← Back</button>
                    <div class="ml-auto flex items-center gap-3">
                        <a href="{{ route('site.borrower.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <button type="button" @click="next()" x-show="step < labels.length - 1"
                                class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Continue</button>
                        <button type="submit" x-show="step === labels.length - 1"
                                class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Submit application</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function applyWizard(products, preselect, applicationFee) {
            return {
                products,
                applicationFee,
                step: 0,
                labels: ['Product', 'Personal', 'Income', 'Review'],
                form: {
                    loan_product_id: preselect ?? (products[0]?.id ?? null),
                    requested_amount: 0,
                    requested_tenure_months: 0,
                    purpose: '',
                },
                current: null,
                init() { this.onProduct(); },
                onProduct() {
                    this.current = this.products.find(p => p.id == this.form.loan_product_id) || this.products[0];
                    if (!this.current) return;
                    if (!this.form.requested_amount || this.form.requested_amount < this.current.min) {
                        this.form.requested_amount = this.current.min;
                    }
                    if (!this.form.requested_tenure_months || this.form.requested_tenure_months < this.current.tmin) {
                        this.form.requested_tenure_months = this.current.tmin;
                    }
                },
                next() {
                    if (this.step === 0) {
                        if (!this.form.loan_product_id) { alert('Select a loan product.'); return; }
                        if (!this.form.purpose || this.form.purpose.length < 5) { alert('Describe how you will use the loan.'); return; }
                    }
                    if (this.step < this.labels.length - 1) { this.step++; window.scrollTo({top: 0, behavior: 'smooth'}); }
                },
                prev() { if (this.step > 0) { this.step--; window.scrollTo({top: 0, behavior: 'smooth'}); } },
                goto(i) { this.step = i; },
                onSubmit(e) {
                    const consent = e.target.elements['consent'];
                    if (consent && !consent.checked) { e.preventDefault(); alert('Please accept the consent.'); }
                },
                formatTzs(v) { return 'TZS ' + new Intl.NumberFormat('en-US').format(Math.round(v || 0)); },
            };
        }
    </script>
</x-site.borrower-layout>
