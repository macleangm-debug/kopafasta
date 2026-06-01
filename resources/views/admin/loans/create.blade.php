<x-admin.layout
    title="Manual loan origination"
    heading="Manual loan origination"
    subheading="Wizard — link an approved application or enter terms for edge cases"
    :backUrl="route('admin.loans.index')"
    backLabel="Back to loans">

    <div class="mb-4 rounded-lg bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm text-slate-800">
        Normal lending starts from <a href="{{ route('admin.loan-applications.create') }}" class="font-semibold text-amber-700 hover:text-amber-800">Applications</a>.
        Use this wizard only when you need to create a <strong>pending loan record</strong> manually — usually from an approved application ready for disbursement.
    </div>

    <div class="admin-loan-origination-wizard space-y-6"
         data-wizard-data-url="{{ $wizardDataUrl }}">

        <div data-wizard-boot-error hidden class="rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
            The loan wizard failed to load. Please refresh the page.
        </div>

        <script type="application/json" id="loan-origination-products">@json($products)</script>

        <nav data-wizard-nav class="flex items-center gap-2 overflow-x-auto pb-1" aria-label="Loan origination steps"></nav>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <form method="POST" action="{{ route('admin.loans.store') }}" data-wizard-form>
                @csrf
                <input type="hidden" name="status" value="pending">
                <input type="hidden" name="loan_application_id" data-application-id value="{{ old('loan_application_id') }}">
                <input type="hidden" name="interest_rate" data-interest-rate value="{{ old('interest_rate') }}">

                @if ($errors->any())
                    <div class="mx-6 mt-6 rounded-lg bg-red-50 ring-1 ring-red-200 p-4 text-sm text-red-700">
                        <strong class="block mb-1">Please fix the following:</strong>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Step 1: Borrower --}}
                <div data-step data-step-label="Borrower" data-step-key="borrower" class="p-6 space-y-5">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Select borrower</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Eligibility and profile are checked before you continue.</p>
                    </div>
                    <div class="max-w-md">
                        <x-admin.select name="customer_id" label="Customer" :options="$customers" required placeholder="— Select customer —" />
                    </div>
                    <div data-eligibility-panel class="hidden rounded-xl ring-1 ring-gray-200 p-4 bg-gray-50">
                        <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Borrower eligibility</p>
                            <span data-eligibility-badge class="text-xs font-semibold rounded-full px-2.5 py-1 bg-gray-100 text-gray-600"></span>
                        </div>
                        <ul data-eligibility-list class="space-y-2 text-sm"></ul>
                    </div>
                </div>

                {{-- Step 2: Application source --}}
                <div data-step data-step-label="Application" data-step-key="application" hidden class="p-6 space-y-5">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Link approved application</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Pick an application at disbursement stage, or continue without one for manual terms.</p>
                    </div>
                    <div data-application-empty class="hidden rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                        No approved applications awaiting a loan for this borrower. Continue to enter product and terms manually.
                    </div>
                    <div data-application-cards class="grid sm:grid-cols-2 gap-3"></div>
                    <button type="button" data-skip-application
                            class="inline-flex text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 ring-1 ring-gray-300 px-4 py-2 rounded-lg">
                        Continue without application →
                    </button>
                </div>

                {{-- Step 3: Product & quote --}}
                <div data-step data-step-label="Product & quote" data-step-key="product" hidden class="p-6 space-y-5">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Loan product & terms</h2>
                        <p class="text-xs text-gray-500 mt-0.5" data-product-hint>Choose product limits and adjust amount and tenure.</p>
                    </div>
                    <div data-application-summary class="hidden rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4 text-sm text-emerald-900 mb-2"></div>
                    <div data-product-cards class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory"></div>
                    <input type="hidden" name="loan_product_id" data-product-id value="{{ old('loan_product_id') }}">
                    <div data-quote-panel class="hidden rounded-xl bg-gray-50 ring-1 ring-gray-200 p-5 space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">Principal amount</span><span class="font-bold" data-quote-amount>TZS 0</span></div>
                            <input type="range" data-range-amount min="0" max="0" step="50000" class="w-full accent-amber-500">
                            <input type="hidden" name="principal_amount" data-input-amount value="{{ old('principal_amount') }}">
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">Tenure (months)</span><span class="font-bold"><span data-quote-tenure>0</span> months</span></div>
                            <input type="range" data-range-tenure min="0" max="0" step="1" class="w-full accent-amber-500">
                            <input type="hidden" name="tenure_months" data-input-tenure value="{{ old('tenure_months') }}">
                        </div>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                            <div class="rounded-lg bg-white ring-1 ring-gray-100 p-3"><p class="text-[10px] uppercase text-gray-500">Monthly installment</p><p class="font-bold text-sm mt-1" data-quote-emi>—</p></div>
                            <div class="rounded-lg bg-white ring-1 ring-gray-100 p-3"><p class="text-[10px] uppercase text-gray-500">Weekly installment</p><p class="font-bold text-sm mt-1" data-quote-weekly>—</p></div>
                            <div class="rounded-lg bg-white ring-1 ring-gray-100 p-3"><p class="text-[10px] uppercase text-gray-500">Interest (est.)</p><p class="font-bold text-sm mt-1" data-quote-interest>—</p></div>
                            <div class="rounded-lg bg-white ring-1 ring-gray-100 p-3"><p class="text-[10px] uppercase text-gray-500">Total repayment</p><p class="font-bold text-sm mt-1" data-quote-total>—</p></div>
                        </div>
                    </div>
                </div>

                {{-- Step 4: Profile --}}
                <div data-step data-step-label="Profile check" data-step-key="profile" hidden class="p-6 space-y-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Profile verification</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Confirm borrower KYC sections before creating the loan record.</p>
                    </div>
                    <ul data-profile-list class="grid sm:grid-cols-2 gap-3"></ul>
                </div>

                {{-- Step 5: Review --}}
                <div data-step data-step-label="Review" data-step-key="review" hidden class="p-6 space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900">Review & create loan</h2>
                    <p class="text-xs text-gray-500">A <strong>pending</strong> loan is created — disburse it from the disbursement queue when ready.</p>
                    <dl data-review-summary class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm rounded-xl bg-gray-50 ring-1 ring-gray-200 p-5"></dl>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                    <a href="{{ route('admin.loans.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</a>
                    <div class="flex items-center gap-2">
                        <button type="button" data-wizard-back hidden class="text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 ring-1 ring-gray-300 px-4 py-2 rounded-lg">Back</button>
                        <button type="button" data-wizard-next class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg">Continue</button>
                        <button type="submit" data-wizard-submit hidden class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-5 py-2 rounded-lg">Create pending loan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.loans._create-wizard-script')
</x-admin.layout>
