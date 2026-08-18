@php
    $contacts = $collection_contacts ?? [];
@endphp

@if (! empty($contacts))
    <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
        <h2 class="font-bold mb-1">Collection contacts</h2>
        <p class="text-xs text-gray-500 mb-4">Call the borrower, guarantor, next of kin, and group members. Record who you reached in the action below.</p>
        <ul class="divide-y divide-gray-100">
            @foreach ($contacts as $row)
                <li class="py-3 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $row['role'] }}</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $row['name'] }}</p>
                        @if (! empty($row['relationship']))
                            <p class="text-xs text-gray-500">{{ $row['relationship'] }}</p>
                        @endif
                        @if (! empty($row['address']))
                            <p class="text-xs text-gray-500 mt-1">{{ $row['address'] }}</p>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        @if (! empty($row['tel']))
                            <a href="{{ $row['tel'] }}" class="inline-flex rounded-lg bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light">
                                Call {{ $row['phone_label'] ?? $row['phone'] }}
                            </a>
                        @else
                            <p class="text-xs text-gray-400">No phone on file</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
