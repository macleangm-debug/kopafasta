<x-admin.layout title="Grade Watch" heading="Grade Watch" subheading="Internal queue. Customers only see that their status is being reviewed.">
    @include('admin.settings._tabs', ['active' => 'grade-watch'])

    <div class="space-y-3">
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">What Grade Watch is</p>
            <p class="mt-1">This queue fills only when a customer’s integrity flags need a staff decision (watch, review, or restricted). Ordinary Bronze/Silver/Gold/Platinum members never appear here. Customers never see this page — they only see that their status is being reviewed.</p>
        </div>
        @forelse ($queue as $customer)
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">{{ $customer->full_name }} · {{ strtoupper($customer->grade ?? 'bronze') }}</p>
                        <p class="text-xs uppercase tracking-widest text-amber-800 mt-1">{{ $customer->grade_integrity }}</p>
                        <p class="text-sm text-gray-600 mt-2">{{ $customer->grade_status === 'under_review' ? 'Status under review during the grace period.' : 'Integrity flags require a staff decision.' }}</p>
                        @foreach ($customer->watch_copy ?? [] as $line)
                            <div class="mt-3 rounded-xl bg-amber-50 ring-1 ring-amber-100 p-3">
                                <p class="text-sm font-semibold text-amber-950">{{ $line['title'] ?? '' }}</p>
                                <p class="text-sm text-amber-900 mt-1">{{ $line['body'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                    <form method="post" action="{{ route('admin.settings.grades.watch.save', $customer) }}" class="space-y-2 min-w-[16rem]">
                        @csrf
                        <select name="action" class="w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="clear">Clear</option>
                            <option value="keep_review">Keep under review</option>
                            <option value="restrict">Restrict progression</option>
                            <option value="escalate">Escalate</option>
                        </select>
                        <textarea name="reason" required minlength="8" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Internal reason"></textarea>
                        <button class="rounded-lg bg-brand text-white text-sm font-semibold px-3 py-2">Save action</button>
                    </form>
                </div>
                <form method="post" action="{{ route('admin.settings.grades.override.save', $customer) }}" class="mt-4 grid sm:grid-cols-3 gap-2">
                    @csrf
                    <select name="grade" class="rounded-lg border-gray-300 text-sm" required>
                        @foreach (['bronze','silver','gold','platinum'] as $grade)
                            <option value="{{ $grade }}">{{ ucfirst($grade) }}</option>
                        @endforeach
                    </select>
                    <input type="datetime-local" name="expires_at" required class="rounded-lg border-gray-300 text-sm">
                    <input name="reason" required minlength="8" placeholder="Override reason" class="rounded-lg border-gray-300 text-sm">
                    <button class="sm:col-span-3 rounded-lg bg-slate-800 text-white text-sm font-semibold px-3 py-2">Set override</button>
                </form>
            </div>
        @empty
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-6 text-sm text-gray-600 space-y-2">
                <p class="font-semibold text-gray-900">No customers currently on Watch, Review or Restricted.</p>
                <p>That is the healthy state. People land here only after the Grade engine raises an integrity flag (for example unusual reversals or rapid facility cycling). Until then, this list stays empty — there is nothing to action.</p>
            </div>
        @endforelse
    </div>
</x-admin.layout>
