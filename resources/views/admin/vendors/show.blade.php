<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->vendor_number"
    :backUrl="route('admin.vendors.index')"
    :editUrl="route('admin.vendors.edit', $record)"
    :fields="array_filter([
        'Vendor #'  => $record->vendor_number,
        'Name'      => $record->name,
        'Category'  => ucfirst(str_replace('_', ' ', $record->category)),
        'Status'    => ucfirst($record->status ?? ''),
        'Phone'     => $record->phone,
        'Email'     => $record->email,
        'Deposit markup %' => $record->deposit_markup_percent,
        'Affiliate code' => $record->affiliate_code,
        'Registration discount %' => $record->registration_discount_percent,
        'Application discount %' => $record->application_discount_percent,
        'Commission %' => $record->affiliate_commission_percent,
        'Recovery commission %' => $record->recovery_commission_percent,
        'Recovery markup %' => $record->recovery_markup_percent,
        'Address'   => ['value' => $record->address, 'wide' => true],
        'Created'   => $record->created_at?->format('Y-m-d H:i'),
    ])" />

@if ($affiliateStats ?? null)
    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate performance</h3>
        <div class="grid sm:grid-cols-3 gap-4 text-sm">
            <div><span class="text-gray-500">Clicks</span><p class="text-xl font-bold">{{ format_number($affiliateStats['clicks']) }}</p></div>
            <div><span class="text-gray-500">Registrations</span><p class="text-xl font-bold">{{ format_number($affiliateStats['registrations']) }}</p></div>
            <div><span class="text-gray-500">Applications</span><p class="text-xl font-bold">{{ format_number($affiliateStats['applications']) }}</p></div>
        </div>
        @if ($record->affiliate_code)
            <p class="mt-4 text-xs text-gray-500">Link: {{ app(\App\Services\AffiliateService::class)->affiliateLink($record) }}</p>
        @endif
    </div>
@endif

@if ($recoveryStats ?? null)
    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Recovery performance</h3>
        <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
            <div><span class="text-gray-500">Assignments</span><p class="text-xl font-bold">{{ format_number($recoveryStats['assignments']) }}</p></div>
            <div><span class="text-gray-500">Active</span><p class="text-xl font-bold">{{ format_number($recoveryStats['active_cases']) }}</p></div>
            <div><span class="text-gray-500">Completed</span><p class="text-xl font-bold">{{ format_number($recoveryStats['completed_cases']) }}</p></div>
            <div><span class="text-gray-500">SLA breaches</span><p class="text-xl font-bold text-red-700">{{ format_number($recoveryStats['sla_breaches']) }}</p></div>
            <div><span class="text-gray-500">Commission earned</span><p class="text-xl font-bold">{{ format_money($recoveryStats['commission_earned']) }}</p></div>
            <div><span class="text-gray-500">Commission paid</span><p class="text-xl font-bold">{{ format_money($recoveryStats['commission_paid']) }}</p></div>
        </div>
    </div>
@endif
