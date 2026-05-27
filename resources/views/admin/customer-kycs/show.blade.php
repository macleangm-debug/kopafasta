@php
    $customer = \App\Models\Customer::find($record->customer_id);
    $reviewer = \App\Models\User::find($record->verified_by);
    $payloadStr = $record->payload ? json_encode($record->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
@endphp
<x-admin.show-page
    :title="'KYC #'.$record->id"
    :heading="'KYC #'.$record->id"
    :subheading="$customer ? trim($customer->first_name.' '.$customer->last_name) : null"
    :backUrl="route('admin.customer-kycs.index')"
    :editUrl="route('admin.customer-kycs.edit', $record)"
    :fields="[
        'Customer'    => $customer ? trim($customer->first_name.' '.$customer->last_name) : null,
        'Status'      => ucfirst(str_replace('_', ' ', $record->status ?? '')),
        'Verified by' => $reviewer?->name,
        'Verified at' => optional($record->verified_at)->format('Y-m-d H:i'),
        'Payload'     => ['value' => $payloadStr, 'wide' => true],
        'Created'     => $record->created_at?->format('Y-m-d H:i'),
    ]" />
