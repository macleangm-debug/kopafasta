<x-admin.show-page
    :title="trim($record->first_name.' '.$record->last_name)"
    :heading="trim($record->first_name.' '.$record->last_name) ?: 'Customer'"
    :subheading="$record->customer_number"
    :backUrl="route('admin.customers.index')"
    :editUrl="route('admin.customers.edit', $record)"
    :fields="[
        'Customer #'      => $record->customer_number,
        'Type'            => ucfirst($record->type ?? ''),
        'Status'          => ucfirst($record->status ?? ''),
        'Phone'           => $record->phone,
        'Email'           => $record->email,
        'National ID'     => $record->national_id,
        'Date of birth'   => optional($record->date_of_birth)->format('Y-m-d'),
        'Branch'          => $record->branch?->name,
        'Employment'      => $record->employment_type,
        'Business name'   => $record->business_name,
        'Monthly income'  => $record->monthly_income ? 'TZS '.number_format((float) $record->monthly_income) : null,
        'Address'         => ['value' => $record->address, 'wide' => true],
        'Onboarded'       => optional($record->onboarded_at)->format('Y-m-d H:i'),
        'Created'         => $record->created_at?->format('Y-m-d H:i'),
    ]" />
