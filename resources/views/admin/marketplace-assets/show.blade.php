<x-admin.show-page
    :title="$record->title"
    :heading="$record->title"
    :subheading="$record->slug"
    :backUrl="route('admin.marketplace-assets.index')"
    :editUrl="route('admin.marketplace-assets.edit', $record)"
    :fields="[
        'Category' => config('asset_marketplace.categories.'.$record->category, $record->category),
        'Supplier' => $record->supplier_name,
        'Asset value' => 'TZS '.format_number($record->asset_value),
        'Supplier deposit' => 'TZS '.format_number($record->supplier_deposit),
        'Markup %' => $record->deposit_markup_percent,
        'Customer deposit' => 'TZS '.format_number($record->customer_deposit ?: $record->computeCustomerDeposit()),
        'Weekly installment' => 'TZS '.format_number($record->weekly_installment),
        'Max tenure' => $record->max_tenure_months.' months',
        'Status' => $record->is_active ? 'Active' : 'Inactive',
    ]" />
