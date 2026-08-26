<x-admin.layout title="Grade Watch" heading="Grade Watch" subheading="Operate real customer integrity cases. Settings Hub still defines Bronze/Gold and grace rules.">
    <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
        <p class="font-semibold">What Grade Watch is</p>
        <p class="mt-1">This queue fills only when a customer’s integrity flags need a staff decision (watch, review, or restricted). Ordinary Bronze/Silver/Gold/Platinum members never appear here. Marketing demos never appear here.</p>
        <p class="mt-2 text-xs"><a href="{{ route('admin.settings.grades') }}" class="font-semibold text-brand hover:underline">Grade calculation lives in Settings → Grades &amp; Trust →</a></p>
    </div>
    <div class="space-y-3">
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
                    <form method="post" action="{{ route('admin.customers.grade-watch.save', $customer) }}" class="space-y-2 min-w-[16rem]"
                          onsubmit="event.preventDefault(); confirmForm(this, { title: 'Save Grade Watch action?' })">
                        @csrf
                        <x-admin.select name="action" label="Action" :options="['clear' => 'Clear', 'keep_review' => 'Keep under review', 'restrict' => 'Restrict progression', 'escalate' => 'Escalate']" required />
                        <x-admin.textarea name="reason" label="Internal reason" required minlength="8" rows="2" placeholder="Internal reason" />
                        <button class="rounded-lg bg-brand text-white text-sm font-semibold px-3 py-2">Save action</button>
                    </form>
                </div>
                <form method="post" action="{{ route('admin.customers.grade-override.save', $customer) }}" class="mt-4 grid sm:grid-cols-3 gap-2"
                      onsubmit="event.preventDefault(); confirmForm(this, { title: 'Set grade override?' })">
                    @csrf
                    <x-admin.select name="grade" label="Override grade" :options="['bronze' => 'Bronze', 'silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum']" required />
                    <input type="datetime-local" name="expires_at" required class="rounded-lg border-gray-300 text-sm">
                    <input name="reason" required minlength="8" placeholder="Override reason" class="rounded-lg border-gray-300 text-sm">
                    <button class="sm:col-span-3 rounded-lg bg-slate-800 text-white text-sm font-semibold px-3 py-2">Set override</button>
                </form>
            </div>
        @empty
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-6 text-sm text-gray-600 space-y-2">
                <p class="font-semibold text-gray-900">No customers currently on Watch, Review or Restricted.</p>
                <p>That is the healthy state. People land here only after the Grade engine raises an integrity flag. Until then, this list stays empty.</p>
            </div>
        @endforelse
    </div>
</x-admin.layout>
