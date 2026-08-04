<dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Activity type</dt><dd class="font-medium mt-0.5">{{ $activityTypes[$customer->activity_type] ?? ($customer->activity_type ?: '—') }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Income range</dt><dd class="font-medium mt-0.5">{{ $incomeRanges[$customer->income_range] ?? ($customer->income_range ?: '—') }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Employment type</dt><dd class="font-medium mt-0.5">{{ $customer->employment_type ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Business / employer</dt><dd class="font-medium mt-0.5">{{ $customer->business_name ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Monthly income</dt><dd class="font-medium mt-0.5">{{ $customer->monthly_income ? format_money($customer->monthly_income) : '—' }}</dd></div>
</dl>
