<x-admin.review-section id="customer-activity" title="Activity & income" subtitle="Employment or business information for underwriting">
    <form method="POST" action="{{ route('admin.customers.section.update', [$customer, 'activity']) }}" class="grid md:grid-cols-2 gap-4">
        @csrf
        @method('PUT')
        <x-admin.select name="activity_type" label="Activity type" :options="$activityTypes" :value="$customer->activity_type" placeholder="— Select —" />
        <x-admin.select name="income_range" label="Income range" :options="$incomeRanges" :value="$customer->income_range" placeholder="— Select —" />
        <x-admin.input name="employment_type" label="Employment type (legacy)" :value="$customer->employment_type" />
        <x-admin.input name="business_name" label="Business / employer name" :value="$customer->business_name" />
        <x-admin.input name="monthly_income" label="Monthly income (TZS)" type="number" step="0.01" :value="$customer->monthly_income" />
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="inline-flex text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg">Save activity</button>
        </div>
    </form>
</x-admin.review-section>
