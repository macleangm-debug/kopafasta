<div id="customer-guarantor-requests" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900">Guarantor requests</h3>
        <p class="text-xs text-gray-500 mt-0.5">Invitations sent for guarantor support on applications</p>
    </div>
    <ul class="divide-y divide-gray-100">
        @forelse ($dossier['guarantor_invitations'] as $invite)
            <li class="px-5 py-4 flex items-center justify-between gap-3 text-sm">
                <div>
                    <p class="font-medium text-gray-900">{{ $invite->invitee_name ?? 'Guarantor' }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $invite->application?->application_number ?? 'Application' }} · {{ ucfirst($invite->status) }}
                    </p>
                </div>
                <span class="text-xs text-gray-500">{{ $invite->created_at?->format('d M Y') }}</span>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-gray-500 text-sm">No guarantor requests.</li>
        @endforelse
    </ul>
</div>
