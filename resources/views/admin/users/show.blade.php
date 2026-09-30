<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="operator_email_display($record->email)"
    :backUrl="route('admin.users.index')"
    :editUrl="auth()->user()?->hasPermission('users.manage') ? route('admin.users.edit', $record) : null"
    :fields="[
        'Name'           => $record->name,
        'Email'          => operator_email_display($record->email),
        'Phone'          => $record->phone,
        'Capabilities'   => $record->roleLabel(),
        'Branch'         => optional(\App\Models\Branch::find($record->branch_id))->name,
        'Approval authority' => $approvalAuthority ?? '—',
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

@if (! empty($supportPerformance))
@php
    $sp = $supportPerformance;
    $fmtMin = function (?int $m): string {
        if ($m === null) {
            return '—';
        }
        if ($m < 60) {
            return $m.'m';
        }

        return intdiv($m, 60).'h '.($m % 60).'m';
    };
@endphp
<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Support performance</p>
            <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Last 30 days · same metrics as Support Reports</h3>
        </div>
        <a href="{{ route('admin.support.performance', ['range' => '30d']) }}" class="text-xs font-semibold text-brand hover:underline">Open charts →</a>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
        @foreach ([
            ['Resolved', $sp['resolved'] ?? 0],
            ['Avg first response', $fmtMin($sp['avg_first_response_minutes'] ?? null)],
            ['SLA met', isset($sp['sla_met']) ? $sp['sla_met'].'%' : '—'],
            ['Avg rating', $sp['customer_rating'] ?? '—'],
            ['Open backlog', $sp['open_backlog'] ?? 0],
        ] as [$label, $value])
            <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $label }}</p>
                <p class="text-xl font-bold text-gray-900 mt-1 tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    @if (! empty($sp['top_issues']))
        <div class="mt-4">
            <p class="text-xs font-semibold text-gray-700 mb-2">Top Issues</p>
            <ul class="divide-y divide-gray-100 rounded-xl ring-1 ring-gray-100 overflow-hidden">
                @foreach ($sp['top_issues'] as $row)
                    <li class="px-3 py-2 flex justify-between text-sm bg-white">
                        <span>{{ str_replace('_', ' ', ucfirst($row['issue'])) }}</span>
                        <span class="font-semibold tabular-nums text-brand">{{ $row['count'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endif

</x-admin.show-page>
