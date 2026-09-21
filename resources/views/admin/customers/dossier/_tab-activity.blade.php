@php
    $activityTypes = $dossier['activity_types'] ?? [];
    $incomeRanges = $dossier['income_ranges'] ?? [];
    $activityDocs = $dossier['documents_by_context']['activity'] ?? collect();
@endphp

<div class="space-y-6">
    <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">What you do / Activity</p>
        <dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Activity type</dt><dd class="font-medium mt-0.5">{{ $activityTypes[$customer->activity_type] ?? ($dossier['activity_label'] ?? ($customer->activity_type ?: '—')) }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Income range</dt><dd class="font-medium mt-0.5">{{ $incomeRanges[$customer->income_range] ?? ($dossier['income_label'] ?? ($customer->income_range ?: '—')) }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Employment type</dt><dd class="font-medium mt-0.5">{{ $customer->employment_type ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Business / employer</dt><dd class="font-medium mt-0.5">{{ $customer->business_name ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Monthly income</dt><dd class="font-medium mt-0.5">{{ $customer->monthly_income ? format_money($customer->monthly_income) : '—' }}</dd></div>
            @if ($customer->business_type)
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Business type</dt><dd class="font-medium mt-0.5">{{ $customer->business_type }}</dd></div>
            @endif
            @if ($customer->employer_name)
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Employer</dt><dd class="font-medium mt-0.5">{{ $customer->employer_name }}</dd></div>
            @endif
            @php
                $activityDefs = activity_fields_localized()[$customer->activity_type ?? $customer->employment_type] ?? [];
                $activityDetails = is_array($customer->activity_details) ? $customer->activity_details : [];
            @endphp
            @foreach ($activityDefs as $field)
                @php
                    $key = $field['key'] ?? null;
                    $val = $key ? ($activityDetails[$key] ?? null) : null;
                @endphp
                @if ($key && filled($val))
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-gray-500">{{ $field['label'] ?? $key }}</dt>
                        <dd class="font-medium mt-0.5">{{ is_array($val) ? implode(', ', $val) : $val }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </div>

    <section class="border-t border-gray-100 pt-5">
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">Income / activity evidence</p>
        @if ($activityDocs->isEmpty())
            <p class="text-sm text-gray-500">No income or activity evidence on file.</p>
        @else
            <ul class="space-y-2">
                @foreach ($activityDocs as $doc)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl ring-1 ring-gray-100 px-3 py-2.5 text-sm">
                        <span class="font-medium">{{ $doc->documentType?->name ?? 'Document' }}</span>
                        <span class="text-xs text-gray-500">{{ display_label($doc->status, 'document_status') }}</span>
                        @if ($doc->file_path)
                            <button type="button"
                                    onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$doc->file_path)), @js($doc->documentType?->name ?? 'Document'), 'image')"
                                    class="text-xs font-semibold text-brand hover:underline">View</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
