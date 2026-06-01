<x-admin.layout
    title="New application"
    heading="New loan application"
    subheading="Smart application wizard — only missing information is required"
    :backUrl="route('admin.loan-applications.index')"
    backLabel="Back to applications">

    <div class="admin-loan-wizard space-y-6"
         data-wizard-data-url="{{ $wizardDataUrl }}">

        <div data-wizard-boot-error hidden class="rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
            The application wizard failed to load. Please refresh the page.
        </div>

        <script type="application/json" id="loan-wizard-products">@json($products)</script>

        <nav data-wizard-nav class="flex items-center gap-2 overflow-x-auto pb-1" aria-label="Application steps"></nav>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <form method="POST" action="{{ route('admin.loan-applications.store') }}" data-wizard-form>
                @csrf

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

                {{-- Step 1: Applicant --}}
                <div data-step data-step-label="Applicant" data-step-key="applicant" class="p-6 space-y-5">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Select borrower</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Eligibility is checked automatically. Incomplete KYC is highlighted before you continue.</p>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <x-admin.select name="customer_id" label="Customer" :options="$customers" required placeholder="— Select customer —" />
                        <x-admin.select name="branch_id" label="Branch" :options="$branches" placeholder="— None —" />
                    </div>
                    <div data-eligibility-panel class="hidden rounded-xl ring-1 ring-gray-200 p-4 bg-gray-50">
                        <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Borrower eligibility</p>
                            <span data-eligibility-badge class="text-xs font-semibold rounded-full px-2.5 py-1 bg-gray-100 text-gray-600"></span>
                        </div>
                        <ul data-eligibility-list class="space-y-2 text-sm"></ul>
                        <p data-eligibility-blocked class="hidden mt-3 text-sm text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
                            Borrower has incomplete mandatory requirements. You can still create a <strong>draft</strong>, or ask them to complete items above first.
                        </p>
                    </div>
                </div>

                {{-- Step 2: Product & quote --}}
                <div data-step data-step-label="Product & quote" data-step-key="product" hidden class="p-6 space-y-5">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Choose loan product</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Limits and rates come from product configuration.</p>
                    </div>
                    <div data-product-cards class="flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory"></div>
                    <input type="hidden" name="loan_product_id" data-product-id value="{{ old('loan_product_id') }}">
                    <div data-quote-panel class="hidden rounded-xl bg-gray-50 ring-1 ring-gray-200 p-5 space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">Loan amount</span><span class="font-bold" data-quote-amount>TZS 0</span></div>
                            <input type="range" data-range-amount min="0" max="0" step="50000" class="w-full accent-amber-500">
                            <input type="hidden" name="requested_amount" data-input-amount value="{{ old('requested_amount') }}">
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-2"><span class="text-gray-600">Tenure (months)</span><span class="font-bold"><span data-quote-tenure>0</span> months</span></div>
                            <input type="range" data-range-tenure min="0" max="0" step="1" class="w-full accent-amber-500">
                            <input type="hidden" name="requested_tenure_months" data-input-tenure value="{{ old('requested_tenure_months') }}">
                        </div>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                            <div class="rounded-lg bg-white ring-1 ring-gray-100 p-3"><p class="text-[10px] uppercase text-gray-500">Monthly installment</p><p class="font-bold text-sm mt-1" data-quote-emi>—</p></div>
                            <div class="rounded-lg bg-white ring-1 ring-gray-100 p-3"><p class="text-[10px] uppercase text-gray-500">Weekly installment</p><p class="font-bold text-sm mt-1" data-quote-weekly>—</p></div>
                            <div class="rounded-lg bg-white ring-1 ring-gray-100 p-3"><p class="text-[10px] uppercase text-gray-500">Interest (est.)</p><p class="font-bold text-sm mt-1" data-quote-interest>—</p></div>
                            <div class="rounded-lg bg-white ring-1 ring-gray-100 p-3"><p class="text-[10px] uppercase text-gray-500">Total repayment</p><p class="font-bold text-sm mt-1" data-quote-total>—</p></div>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Profile verification --}}
                <div data-step data-step-label="Profile check" data-step-key="profile" hidden class="p-6 space-y-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Profile verification</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Completed sections are marked verified — only gaps need attention on the borrower profile.</p>
                    </div>
                    <ul data-profile-list class="grid sm:grid-cols-2 gap-3"></ul>
                </div>

                {{-- Step 4: Application details --}}
                <div data-step data-step-label="Application" data-step-key="details" hidden class="p-6 space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Purpose</label>
                            <select name="purpose" class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">
                                <option value="">— Select purpose —</option>
                                @foreach ($loanPurposes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('purpose') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-admin.input name="recommended_amount" label="Recommended amount (optional)" :value="old('recommended_amount')" type="number" />
                        <x-admin.input name="application_number" label="Application #" :value="old('application_number')" placeholder="Auto-generated if blank" />
                        <x-admin.select name="status" label="Status" :options="$statuses" :value="old('status', 'submitted')" required />
                        <x-admin.input name="current_stage" label="Current stage" :value="old('current_stage', 'submitted')" />
                        <div class="md:col-span-2">
                            <x-admin.textarea name="rejection_reason" label="Rejection reason (if applicable)" :value="old('rejection_reason')" rows="2" />
                        </div>
                    </div>
                </div>

                {{-- Step 5: Review --}}
                <div data-step data-step-label="Review" data-step-key="review" hidden class="p-6 space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900">Review & create</h2>
                    <dl data-review-summary class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm rounded-xl bg-gray-50 ring-1 ring-gray-200 p-5"></dl>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                    <a href="{{ route('admin.loan-applications.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</a>
                    <div class="flex items-center gap-2">
                        <button type="button" data-wizard-back hidden class="text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 ring-1 ring-gray-300 px-4 py-2 rounded-lg">Back</button>
                        <button type="button" data-wizard-next class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg">Continue</button>
                        <button type="submit" data-wizard-submit hidden class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-5 py-2 rounded-lg">Create application</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.loan-applications._create-wizard-script')
</x-admin.layout>
