<x-admin.layout title="Credit team" heading="Credit team" subheading="Underwriting analysts and credit committee members — same accounts as Settings → Users">

    <div class="mb-6 rounded-xl bg-gradient-to-r from-brand/5 to-white ring-1 ring-brand/10 px-5 py-4 text-sm text-gray-700">
        <p class="font-semibold text-gray-900">How the dual-approval flow works</p>
        <ol class="mt-2 list-decimal ml-5 space-y-1 text-gray-600">
            <li><span class="font-medium text-gray-800">Credit analysts</span> (Underwriting) review applications and submit a <strong>recommendation</strong> (approve / counter / recommend decline) with reasons.</li>
            <li><span class="font-medium text-gray-800">Credit committee</span> members see that recommendation on the credit file, then make the <strong>final decision</strong> (approve, issue offer, or reject).</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">
            Team members are real admin users with roles <code class="text-[11px]">credit_analyst</code> or <code class="text-[11px]">credit_committee</code>.
            Add or edit them in
            <a href="{{ route('admin.users.index') }}" class="font-semibold text-brand hover:underline">Settings → Users</a>
            so permissions stay consistent.
        </p>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Step 1</p>
                    <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Underwriting · Credit analysts</h2>
                    @if ($underwriting)
                        <p class="text-xs text-gray-500 mt-1">Department: {{ $underwriting->name }} ({{ $underwriting->code }})</p>
                    @endif
                </div>
                <a href="{{ route('admin.loan-applications.pipeline.under-review') }}"
                   class="text-xs font-semibold text-brand hover:underline shrink-0">Open queue →</a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($analysts as $user)
                    <li class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }} · {{ display_label($user->role, 'role') }}</p>
                        </div>
                        <a href="{{ route('admin.users.edit', $user) }}" class="text-xs font-semibold text-gray-600 hover:text-gray-900">Edit</a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-sm text-gray-500 text-center">No credit analysts yet.</li>
                @endforelse
            </ul>
            <div class="border-t border-gray-100 px-5 py-4 bg-gray-50/70 flex flex-wrap gap-3">
                <a href="{{ route('admin.users.create', ['role' => 'credit_analyst']) }}"
                   class="inline-flex items-center gap-2 text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-lg">
                    + Add credit analyst in Users
                </a>
                <a href="{{ route('admin.users.index', ['role' => 'credit_analyst']) }}"
                   class="inline-flex items-center text-xs font-semibold text-gray-600 hover:text-gray-900">
                    Manage users →
                </a>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Step 2</p>
                    <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Credit committee</h2>
                    @if ($committee)
                        <p class="text-xs text-gray-500 mt-1">Department: {{ $committee->name }} ({{ $committee->code }})</p>
                    @endif
                </div>
                <a href="{{ route('admin.loan-applications.pre-approvals') }}"
                   class="text-xs font-semibold text-brand hover:underline shrink-0">Open queue →</a>
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
            <div class="border-t border-gray-100 px-5 py-4 bg-gray-50/70 flex flex-wrap gap-3">
                <a href="{{ route('admin.users.create', ['role' => 'credit_committee']) }}"
                   class="inline-flex items-center gap-2 text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-lg">
                    + Add committee member in Users
                </a>
                <a href="{{ route('admin.users.index', ['role' => 'credit_committee']) }}"
                   class="inline-flex items-center text-xs font-semibold text-gray-600 hover:text-gray-900">
                    Manage users →
                </a>
            </div>
        </section>
    </div>

</x-admin.layout>
