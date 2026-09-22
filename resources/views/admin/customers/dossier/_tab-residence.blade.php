@php
    $residenceDocs = $dossier['documents_by_context']['residence'] ?? collect();
@endphp

<div class="space-y-6">
    <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">Where you live</p>
        <dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Region</dt><dd class="font-medium mt-0.5">{{ $customer->region ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">District</dt><dd class="font-medium mt-0.5">{{ $customer->district ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Ward</dt><dd class="font-medium mt-0.5">{{ $customer->ward ?: '—' }}</dd></div>
            <div class="md:col-span-2"><dt class="text-xs uppercase tracking-wider text-gray-500">Street / plot / house no.</dt><dd class="font-medium mt-0.5">{{ $customer->street ?: '—' }}</dd></div>
            @if ($customer->address)
                <div class="md:col-span-2"><dt class="text-xs uppercase tracking-wider text-gray-500">Address</dt><dd class="font-medium mt-0.5">{{ $customer->address }}</dd></div>
            @endif
            @if (filled($customer->lga_officer_name) || filled($customer->lga_officer_position) || filled($customer->lga_officer_phone))
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">LGA officer</dt><dd class="font-medium mt-0.5">{{ $customer->lga_officer_name ?: '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">LGA position</dt><dd class="font-medium mt-0.5">{{ $customer->lga_officer_position ?: '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">LGA phone</dt><dd class="font-medium mt-0.5">{{ $customer->lga_officer_phone ?: '—' }}</dd></div>
            @endif
        </dl>
    </div>

    <section class="border-t border-gray-100 pt-5">
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">Residence proof</p>
        @if ($residenceDocs->isEmpty())
            <p class="text-sm text-gray-500">No residence proof on file.</p>
        @else
            <ul class="space-y-2">
                @foreach ($residenceDocs as $doc)
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
