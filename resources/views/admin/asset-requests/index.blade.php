<x-admin.layout title="Asset Requests" heading="Asset Requests" subheading="Borrower requests for unavailable assets">
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Asset</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Budget</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($requests as $requestRow)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $requestRow->asset_name }}</td>
                        <td class="px-4 py-3">{{ $requestRow->customer?->full_name }}</td>
                        <td class="px-4 py-3">{{ format_money($requestRow->budget ?? 0) }}</td>
                        <td class="px-4 py-3">{{ ucfirst($requestRow->status) }}</td>
                        <td class="px-4 py-3">{{ $requestRow->vendor?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.asset-requests.update', $requestRow) }}" class="flex flex-wrap gap-2 items-center">
                                @csrf @method('PUT')
                                <select name="status" class="rounded border-gray-300 text-xs">
                                    @foreach (['pending','reviewing','matched','closed'] as $status)
                                        <option value="{{ $status }}" @selected($requestRow->status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                                <select name="vendor_id" class="rounded border-gray-300 text-xs">
                                    <option value="">Assign supplier</option>
                                    @foreach ($suppliers as $id => $name)
                                        <option value="{{ $id }}" @selected($requestRow->vendor_id == $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                <button class="text-xs font-semibold text-amber-700">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-admin.layout>
