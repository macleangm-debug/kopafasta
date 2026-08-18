<x-admin.layout title="Credit teams" heading="" subheading="">
    <x-admin.letterhead kicker="Credit desk" title="Credit teams" subtitle="Three teams own the loan journey — screening, committee decision, then credit management through disbursement" />

    <div class="mb-6 rounded-xl bg-gradient-to-r from-brand/5 to-white ring-1 ring-brand/10 px-5 py-4 text-sm text-gray-700">
        <p class="font-semibold text-gray-900">How the three teams work</p>
        <ol class="mt-2 list-decimal ml-5 space-y-1 text-gray-600">
            <li><span class="font-medium text-gray-800">Credit screening</span> — documents, face/ID, affordability, and recommendation.</li>
            <li><span class="font-medium text-gray-800">Credit committee</span> — final credit decision (approve, counter-offer, or reject).</li>
            <li><span class="font-medium text-gray-800">Credit management</span> — after approval: offer → fees → contract → disbursement queue, then servicing through arrears.</li>
        </ol>
        <p class="mt-3 text-xs text-amber-950 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
            <span class="font-semibold">Separation rule:</span> one person cannot be on Screening and Committee at the same time.
            Committee + Management is fine. Admin / Super admin may hold any combination.
        </p>
        <p class="mt-3 text-xs text-gray-500">
            Members are admin users (<code class="text-[11px]">credit_analyst</code>, <code class="text-[11px]">credit_committee</code>, <code class="text-[11px]">manager</code>).
            You can also manage them in
            <a href="{{ route('admin.users.index') }}" class="font-semibold text-brand hover:underline">Settings → Users</a>.
        </p>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Team 1</p>
                    <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Credit screening</h2>
                    @if ($underwriting)
                        <p class="text-xs text-gray-500 mt-1">{{ $underwriting->name }} ({{ $underwriting->code }})</p>
                    @endif
                </div>
                <a href="{{ route('admin.loan-applications.pipeline.under-review') }}"
                   class="text-xs font-semibold text-brand hover:underline shrink-0">Queue →</a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($screening as $user)
                    <li class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }} · {{ display_label($user->role, 'role') }}</p>
                        </div>
                        <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-semibold text-gray-600 hover:text-gray-900">Edit</a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-sm text-gray-500 text-center">No screening analysts yet.</li>
                @endforelse
            </ul>
            @include('admin.credit-team._add-member-form', [
                'team' => 'screening',
                'title' => 'Add screening analyst',
                'branches' => $branches,
            ])
            <div class="border-t border-gray-100 px-5 py-3 bg-gray-50/70">
                <a href="{{ route('admin.users.create', ['role' => 'credit_analyst']) }}"
                   class="text-xs font-semibold text-brand hover:underline">Or create in Users →</a>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Team 2</p>
                    <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Credit committee</h2>
                    @if ($committee)
                        <p class="text-xs text-gray-500 mt-1">{{ $committee->name }} ({{ $committee->code }})</p>
                    @endif
                </div>
                <a href="{{ route('admin.loan-applications.pre-approvals') }}"
                   class="text-xs font-semibold text-brand hover:underline shrink-0">Queue →</a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($committeeMembers as $user)
                    <li class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }} · {{ display_label($user->role, 'role') }}</p>
                        </div>
                        <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-semibold text-gray-600 hover:text-gray-900">Edit</a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-sm text-gray-500 text-center">No committee members yet.</li>
                @endforelse
            </ul>
            @include('admin.credit-team._add-member-form', [
                'team' => 'committee',
                'title' => 'Add committee member',
                'branches' => $branches,
            ])
            <div class="border-t border-gray-100 px-5 py-3 bg-gray-50/70">
                <a href="{{ route('admin.users.create', ['role' => 'credit_committee']) }}"
                   class="text-xs font-semibold text-brand hover:underline">Or create in Users →</a>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden ring-brand/20">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 bg-brand-muted/20">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Team 3</p>
                    <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Credit management</h2>
                    @if ($management)
                        <p class="text-xs text-gray-500 mt-1">{{ $management->name }} ({{ $management->code }})</p>
                    @endif
                </div>
                <a href="{{ route('admin.loan-applications.pipeline.approved') }}"
                   class="text-xs font-semibold text-brand hover:underline shrink-0">Queue →</a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($managers as $user)
                    <li class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }} · {{ display_label($user->role, 'role') }}</p>
                        </div>
                        <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-semibold text-gray-600 hover:text-gray-900">Edit</a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-sm text-gray-500 text-center">No credit managers yet. Add the team below.</li>
                @endforelse
            </ul>
            @include('admin.credit-team._add-member-form', [
                'team' => 'management',
                'title' => 'Add credit manager',
                'branches' => $branches,
            ])
            <div class="border-t border-gray-100 px-5 py-3 bg-gray-50/70 flex flex-wrap gap-3">
                <a href="{{ route('admin.users.create', ['role' => 'manager']) }}"
                   class="text-xs font-semibold text-brand hover:underline">Or create in Users →</a>
                <a href="{{ route('admin.loans.disbursement') }}"
                   class="text-xs font-semibold text-gray-600 hover:text-gray-900">Disbursement queue →</a>
            </div>
        </section>
    </div>

</x-admin.layout>
