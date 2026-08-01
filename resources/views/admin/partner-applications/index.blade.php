<x-admin.layout title="Partner Applications" heading="Partner applications" subheading="Public partner enrollments awaiting review">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.partners.index') }}" class="text-sm font-semibold text-brand hover:underline">← Partners hub</a>
        <div class="flex flex-wrap gap-2 text-xs">
            @foreach ([
                '' => 'All',
                'collection' => 'Collection',
                'service' => 'Service',
                'affiliate' => 'Affiliate',
            ] as $value => $label)
                <a href="{{ route('admin.partner-applications.index', array_filter(['type' => $value ?: null, 'status' => $filterStatus])) }}"
                   class="rounded-full px-3 py-1.5 font-semibold {{ ($filterType ?? '') === $value ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ $label }}
                </a>
            @endforeach
            @foreach (['pending', 'approved', 'rejected'] as $status)
                <a href="{{ route('admin.partner-applications.index', array_filter(['type' => $filterType ?: null, 'status' => $status])) }}"
                   class="rounded-full px-3 py-1.5 font-semibold {{ ($filterStatus ?? '') === $status ? 'bg-brand text-white' : 'ring-1 ring-gray-200 text-gray-600 hover:bg-gray-50' }}">
                    {{ ucfirst($status) }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        @if ($applications->isEmpty())
            <p class="px-6 py-10 text-sm text-gray-500 text-center">No partner applications yet.</p>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Applicant</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Business</th>
                        <th class="px-4 py-3">Docs</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($applications as $application)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">{{ $application->categoryLabel() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $application->full_name }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst($application->applicant_category) }} · {{ $application->region ?: '—' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ $application->email }}</p>
                                <p class="text-xs text-gray-500">{{ $application->phone }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $application->business_name ?: '—' }}</p>
                                @if ($application->tin || $application->registration_number)
                                    <p class="text-xs text-gray-500">TIN {{ $application->tin ?: '—' }} · Reg {{ $application->registration_number ?: '—' }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $application->documents->count() }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badge = match ($application->status) {
                                        'approved' => 'bg-emerald-100 text-emerald-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        default    => 'bg-amber-100 text-amber-800',
                                    };
                                @endphp
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ ucfirst($application->status) }}</span>
                                @if ($application->partner_id)
                                    <p class="text-[11px] text-emerald-700 mt-1">Partner linked</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.partner-applications.show', $application) }}" class="text-xs font-semibold text-brand hover:underline">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $applications->links() }}</div>
</x-admin.layout>
