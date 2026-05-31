<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->email"
    :backUrl="route('admin.users.index')"
    :editUrl="auth()->user()?->hasPermission('users.manage') ? route('admin.users.edit', $record) : null"
    :fields="[
        'Name'           => $record->name,
        'Email'          => $record->email,
        'Phone'          => $record->phone,
        'Role'           => ucfirst(str_replace('_', ' ', $record->role ?? '')),
        'Branch'         => optional(\App\Models\Branch::find($record->branch_id))->name,
        'Approval limit' => $record->approval_limit ? 'TZS '.number_format((float) $record->approval_limit) : null,
        'Account status' => $record->is_active ? 'Active' : 'Inactive',
        'Locked until'   => $isLocked ? $record->locked_until?->format('d M Y, H:i') : null,
        'Created'        => $record->created_at?->format('Y-m-d H:i'),
    ]">

@perm('users.manage')
<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-1">Account access</h3>
    <p class="text-xs text-gray-500 mb-4">Lock blocks sign-in until the expiry time. Deactivate keeps the record but prevents access.</p>

    <div class="flex flex-wrap gap-2 mb-6">
        @if ($isLocked)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                Locked until {{ $record->locked_until->format('d M Y, H:i') }}
            </span>
        @else
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Not locked</span>
        @endif
        @if (! $record->is_active)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Deactivated</span>
        @endif
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        @if (! $isLocked)
            <form method="POST" action="{{ route('admin.users.lock', $record) }}" class="space-y-3 rounded-lg border border-gray-200 p-4">
                @csrf
                <p class="text-sm font-semibold text-gray-800">Lock account</p>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Duration (minutes)</label>
                    <input type="number" name="minutes" value="60" min="1" max="43200"
                           class="w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Reason (optional)</label>
                    <input type="text" name="reason" maxlength="500" placeholder="e.g. Suspicious activity"
                           class="w-full rounded-lg border-gray-200 text-sm">
                </div>
                <button type="submit" class="inline-flex items-center text-sm font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-4 py-2 rounded-lg">
                    Lock account
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.users.unlock', $record) }}" class="rounded-lg border border-gray-200 p-4">
                @csrf
                <p class="text-sm font-semibold text-gray-800 mb-3">This account is locked</p>
                <button type="submit" class="inline-flex items-center text-sm font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 px-4 py-2 rounded-lg">
                    Unlock account
                </button>
            </form>
        @endif

        @if ((int) $record->id !== (int) auth()->id())
            <form method="POST" action="{{ route('admin.users.toggle-active', $record) }}" class="rounded-lg border border-gray-200 p-4">
                @csrf
                <p class="text-sm font-semibold text-gray-800 mb-1">
                    {{ $record->is_active ? 'Deactivate account' : 'Activate account' }}
                </p>
                <p class="text-xs text-gray-500 mb-3">
                    {{ $record->is_active
                        ? 'User will not be able to sign in while inactive.'
                        : 'Restore sign-in access for this user.' }}
                </p>
                <button type="submit" @class([
                    'inline-flex items-center text-sm font-semibold px-4 py-2 rounded-lg',
                    'text-red-800 bg-red-100 hover:bg-red-200' => $record->is_active,
                    'text-emerald-800 bg-emerald-100 hover:bg-emerald-200' => ! $record->is_active,
                ])>
                    {{ $record->is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
        @endif
    </div>
</div>
@endperm

</x-admin.show-page>
