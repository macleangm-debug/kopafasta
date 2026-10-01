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

<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5" id="password-access">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Password</h3>
        <p class="text-xs text-gray-500 mt-1">Set a temporary password or issue a secure setup link. Current password is never shown.</p>
    </div>
    @if (session('temporary_password'))
        <p class="text-sm text-emerald-800 bg-emerald-50 ring-1 ring-emerald-200 rounded-lg px-3 py-2">
            Temporary password (show once): <span class="font-mono font-bold">{{ session('temporary_password') }}</span>
        </p>
    @endif
    @if (session('password_setup_url'))
        <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 p-4 space-y-2" data-testid="password-setup-url">
            <p class="text-xs font-semibold text-brand">Setup / reset link (expires {{ session('password_setup_expires') }})</p>
            <input type="text" readonly value="{{ session('password_setup_url') }}"
                   class="w-full rounded-lg border-gray-200 text-xs font-mono bg-white"
                   onclick="this.select()" id="password-setup-url-field">
            <p class="text-[11px] text-gray-500">Copy and share out-of-band when the user has no email.</p>
        </div>
    @endif
    <div class="grid lg:grid-cols-2 gap-4">
        <form method="POST" action="{{ route('admin.users.reset-password', $record) }}" class="space-y-3 rounded-xl ring-1 ring-gray-200 p-4"
              x-data="{ show: false }"
              onsubmit="event.preventDefault(); confirmForm(this, {
                  title: 'Reset staff password?',
                  message: 'This replaces the current password with a temporary password you can share securely.',
                  confirmLabel: 'Set password',
                  confirmClass: 'bg-brand hover:brightness-95 text-white',
              })">
            @csrf
            <div class="relative">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Optional temporary password</label>
                <input :type="show ? 'text' : 'password'" name="password" autocomplete="new-password"
                       class="w-full rounded-lg border-gray-200 text-sm pr-16" placeholder="Leave blank to auto-generate">
                <button type="button" @click="show = !show" class="absolute right-2 bottom-2 text-xs font-semibold text-brand">Show</button>
            </div>
            <div class="relative" x-data="{ show2: false }">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Confirm temporary password</label>
                <input :type="show2 ? 'text' : 'password'" name="password_confirmation" autocomplete="new-password"
                       class="w-full rounded-lg border-gray-200 text-sm pr-16">
                <button type="button" @click="show2 = !show2" class="absolute right-2 bottom-2 text-xs font-semibold text-brand">Show</button>
            </div>
            <button type="submit" data-loading-label="Setting…"
                    class="inline-flex rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5 hover:brightness-95">
                Set password
            </button>
        </form>
        <form method="POST"
              action="{{ route('admin.users.password-setup-link', $record) }}"
              id="admin-password-setup-link-form"
              class="space-y-3 rounded-xl ring-1 ring-gray-200 p-4"
              data-testid="password-setup-link-form">
            @csrf
            <p class="text-sm font-semibold text-gray-900">Secure setup link</p>
            <p class="text-xs text-gray-500">User chooses their own password. Link is single-use and expires.</p>
            {{-- Hidden real submit for confirmForm → form.submit() + loader binding. Visible CTA is type=button. --}}
            <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true" data-loading-label="Creating…">Create link</button>
            <button type="button"
                    data-loading-label="Creating…"
                    data-testid="password-setup-link-cta"
                    class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5 hover:brightness-95 kf-press"
                    onclick="confirmForm(document.getElementById('admin-password-setup-link-form'), { title: 'Issue password setup link?', message: 'Creates a single-use link that expires. Share it securely. Never invent an email address.', confirmLabel: 'Create link', confirmClass: 'bg-brand-gold text-brand hover:brightness-95' })">
                Create / send setup link
            </button>
        </form>
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
@else
<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Performance</p>
    <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Role metrics</h3>
    <p class="text-sm text-gray-500 mt-2">No role-specific performance metrics are wired for this staff desk yet. Support agents show Support Reports here.</p>
</div>
@endif

</x-admin.show-page>
