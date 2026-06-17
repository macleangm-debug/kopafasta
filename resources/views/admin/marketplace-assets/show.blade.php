<x-admin.show-page
    :title="$record->title"
    :heading="$record->title"
    :subheading="$record->slug"
    :backUrl="route('admin.marketplace-assets.index')"
    :editUrl="route('admin.marketplace-assets.edit', $record)"
    :fields="[
        'Category' => config('asset_marketplace.categories.'.$record->category, $record->category),
        'Supplier' => $record->supplier_name,
        'Asset value' => format_money($record->asset_value),
        'Supplier deposit' => format_money($record->supplier_deposit),
        'Markup %' => $record->deposit_markup_percent,
        'Company markup' => format_money(app(\App\Services\AssetLendingService::class)->depositMarkupAmount($record)),
        'Customer deposit' => format_money($record->customer_deposit ?: $record->computeCustomerDeposit()),
        'Weekly installment' => format_money($record->weekly_installment),
        'Max tenure' => $record->max_tenure_months.' months',
        'Waiting period' => ($record->waiting_period_days ?? '—').' days',
        'Insurance policy' => $record->insurance_policy_number ?: '—',
        'Insurance expiry' => optional($record->insurance_expires_at)->format('d M Y') ?: '—',
        'Status' => $record->is_active ? 'Active' : 'Inactive',
        'Availability' => ucfirst($record->availability_status ?? 'available'),
    ]">
    @if (! empty($record->photos))
        <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($record->photos as $photo)
                <img src="{{ Storage::url($photo) }}" alt="" class="rounded-lg ring-1 ring-gray-200 aspect-square object-cover">
            @endforeach
        </div>
    @endif
</x-admin.show-page>
