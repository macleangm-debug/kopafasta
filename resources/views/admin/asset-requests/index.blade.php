<x-admin.layout title="Asset Requests" heading="" subheading="">
    <x-admin.letterhead kicker="Assets" title="Asset requests" subtitle="Borrower requests arrive here first — approve by assigning a supplier before they appear in the supplier portal" />
@if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">1) Review request · 2) Assign supplier (releases to their portal) · 3) Create listing when matched.</p>
        <a href="{{ route('admin.marketplace-assets.create') }}" class="text-sm font-semibold text-brand hover:underline">New marketplace asset →</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        @if ($requests->isEmpty())
            <p class="px-6 py-12 text-sm text-gray-500 text-center">No asset requests yet.</p>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Asset</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Budget / tenure</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($requests as $requestRow)
                        @php
                            $statusBadge = match ($requestRow->status) {
                                'matched'   => 'bg-emerald-100 text-emerald-800',
                                'reviewing' => 'bg-sky-100 text-sky-800',
                                'closed'    => 'bg-gray-100 text-gray-700',
                                'sourcing'  => 'bg-amber-100 text-amber-800',
                                default     => 'bg-amber-100 text-amber-800',
                            };
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $requestRow->asset_name }}</p>
                                @if ($requestRow->description)
                                    <p class="text-xs text-gray-500 mt-1 max-w-xs">{{ $requestRow->description }}</p>
                                @endif
                                @if ($requestRow->photo_path)
                                    <a href="{{ asset('storage/'.$requestRow->photo_path) }}" target="_blank" class="text-xs font-semibold text-brand hover:underline mt-1 inline-block">View photo</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $requestRow->customer?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <p>{{ format_money($requestRow->budget ?? 0) }}</p>
                                @if ($requestRow->preferred_tenure_months)
                                    <p class="text-xs text-gray-500">{{ $requestRow->preferred_tenure_months }} months</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusBadge }}">{{ $statuses[$requestRow->status] ?? ucfirst($requestRow->status) }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $requestRow->vendor?->name ?? '—' }}</td>
                            <td class="px-4 py-3 min-w-[16rem]">
                                <form method="POST" action="{{ route('admin.asset-requests.update', $requestRow) }}" class="space-y-2">
                                    @csrf @method('PUT')
                                    <select name="status" class="w-full rounded border-gray-300 text-xs">
                                        @foreach (['sourcing','reviewing','matched','closed'] as $status)
                                            <option value="{{ $status }}" @selected($requestRow->status === $status)>{{ $statuses[$status] ?? ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                    <select name="vendor_id" class="w-full rounded border-gray-300 text-xs">
                                        <option value="">{{ $requestRow->status === 'closed' || $requestRow->status === 'sourcing' ? 'Assign supplier…' : 'Assign supplier (required to release)' }}</option>
                                        @foreach ($suppliers as $id => $name)
                                            <option value="{{ $id }}" @selected($requestRow->vendor_id == $id)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="admin_notes" value="{{ $requestRow->admin_notes }}" placeholder="Admin notes" class="w-full rounded border-gray-300 text-xs">
                                    <div class="flex flex-wrap gap-2 items-center">
                                        <button class="text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">Approve / save</button>
                                        <a href="{{ route('admin.marketplace-assets.create', [
                                                'title' => $requestRow->asset_name,
                                                'asset_value' => $requestRow->budget,
                                                'max_tenure_months' => $requestRow->preferred_tenure_months,
                                                'vendor_id' => $requestRow->vendor_id,
                                            ]) }}"
                                           class="text-xs font-semibold text-sky-700 hover:underline">Create listing</a>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-admin.layout>
