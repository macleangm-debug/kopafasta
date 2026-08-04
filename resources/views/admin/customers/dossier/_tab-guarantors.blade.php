@if (($dossier['guarantor_invitations'] ?? collect())->isEmpty())
    <p class="text-sm text-gray-500">No guarantor requests on file.</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-gray-500 border-b border-gray-100">
                <tr>
                    <th class="py-2 text-left">Application</th>
                    <th class="py-2 text-left">Status</th>
                    <th class="py-2 text-left">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($dossier['guarantor_invitations'] as $invite)
                    <tr>
                        <td class="py-3 font-mono text-xs">{{ $invite->application?->application_number ?? '—' }}</td>
                        <td class="py-3 capitalize">{{ str_replace('_', ' ', (string) ($invite->status ?? '—')) }}</td>
                        <td class="py-3 text-gray-500">{{ $invite->created_at?->format('d M Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
