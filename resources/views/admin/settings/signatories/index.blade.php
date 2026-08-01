<x-admin.layout title="Signatories" heading="Company signatories" subheading="Authorised signatories for contracts and offer letters">
    @include('admin.settings._tabs', ['active' => 'signatories'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.settings.signatories.create') }}"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">
            + Add signatory
        </a>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Name</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3">Position</th>
                    <th class="px-5 py-3">Email</th>
                    <th class="px-5 py-3">Signature</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($signatories as $signatory)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium">{{ $signatory->name }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ ucfirst(str_replace('_', ' ', $signatory->signatory_type ?? 'company')) }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $signatory->position ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $signatory->email ?? '—' }}</td>
                        <td class="px-5 py-3">
                            @if ($signatory->signature_path)
                                <img src="{{ $signatory->signaturePublicUrl() }}" alt="" class="h-10 object-contain">
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $signatory->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $signatory->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right space-x-2">
                            <a href="{{ route('admin.settings.signatories.edit', $signatory) }}" class="text-brand hover:underline text-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.settings.signatories.destroy', $signatory) }}" class="inline"
                                  onsubmit="return confirm('Remove this signatory?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No signatories configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
