<x-admin.layout title="Affiliate Applications" heading="Affiliate applications" subheading="Public become-an-affiliate submissions awaiting review">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-4">
        <a href="{{ route('admin.partners.index') }}" class="text-sm font-semibold text-amber-700 hover:underline">← Partners hub</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        @if ($applications->isEmpty())
            <p class="px-6 py-10 text-sm text-gray-500 text-center">No affiliate applications yet.</p>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Applicant</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Region</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($applications as $application)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $application->full_name }}</p>
                                @if ($application->business_name)
                                    <p class="text-xs text-gray-500">{{ $application->business_name }}</p>
                                @endif
                                @if ($application->message)
                                    <p class="text-xs text-gray-600 mt-1 max-w-md">{{ $application->message }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ $application->email }}</p>
                                <p class="text-xs text-gray-500">{{ $application->phone }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $application->region ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badge = match ($application->status) {
                                        'approved' => 'bg-emerald-100 text-emerald-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        default    => 'bg-amber-100 text-amber-800',
                                    };
                                @endphp
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ ucfirst($application->status) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.partner-applications.update', $application) }}" class="space-y-2 min-w-[14rem]">
                                    @csrf @method('PUT')
                                    <select name="status" class="w-full rounded border-gray-300 text-xs">
                                        @foreach (['pending', 'approved', 'rejected'] as $status)
                                            <option value="{{ $status }}" @selected($application->status === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="admin_notes" value="{{ $application->admin_notes }}" placeholder="Admin notes" class="w-full rounded border-gray-300 text-xs">
                                    <button class="text-xs font-semibold text-amber-700">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $applications->links() }}</div>
</x-admin.layout>
