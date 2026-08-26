<x-admin.layout title="Audiences" heading="Audiences" subheading="Reusable groups of real customers. Filters come from Settings Hub dimensions.">
    <form method="get" action="{{ route('admin.growth.audiences.estimate') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 mb-6 space-y-4">
        <h2 class="font-semibold text-gray-900">Who should receive this?</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-admin.select name="country_code" label="Country" :options="['' => 'Any'] + ($dimensions['country_code']['options'] ?? [])" :value="$filters['country_code'] ?? ''" />
            <x-admin.select name="status" label="Customer status" :options="['' => 'Any'] + ($dimensions['status']['options'] ?? [])" :value="$filters['status'] ?? 'active'" />
            <div>
                <p class="text-xs font-semibold text-gray-700 mb-1">Grade</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($dimensions['grades']['options'] ?? [] as $value => $label)
                        <label class="inline-flex items-center gap-1.5 text-sm">
                            <input type="checkbox" name="grades[]" value="{{ $value }}" class="rounded border-gray-300 text-brand" @checked(in_array($value, $filters['grades'] ?? [], true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <x-admin.select name="plus" label="Kopafasta Plus" :options="$dimensions['plus']['options'] ?? []" :value="$filters['plus'] ?? 'any'" />
            <x-admin.select name="borrowing" label="Borrowing relationship" :options="$dimensions['borrowing']['options'] ?? []" :value="$filters['borrowing'] ?? 'any'" />
            <x-admin.select name="affiliate" label="Affiliate status" :options="$dimensions['affiliate']['options'] ?? []" :value="$filters['affiliate'] ?? 'any'" />
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-brand">
                Estimated audience:
                @if ($estimate !== null)
                    {{ \App\Support\MoneyFormat::compact($estimate) }} people
                    <span class="text-xs font-normal text-gray-500">({{ number_format($estimate) }})</span>
                @else
                    run estimate
                @endif
            </p>
            <button class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-2 text-sm font-semibold">Estimate</button>
        </div>
    </form>

    <form method="post" action="{{ route('admin.growth.audiences.store') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 mb-6 space-y-3">
        @csrf
        <input type="hidden" name="country_code" value="{{ $filters['country_code'] ?? '' }}">
        <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
        <input type="hidden" name="plus" value="{{ $filters['plus'] ?? 'any' }}">
        <input type="hidden" name="borrowing" value="{{ $filters['borrowing'] ?? 'any' }}">
        <input type="hidden" name="affiliate" value="{{ $filters['affiliate'] ?? 'any' }}">
        @foreach ($filters['grades'] ?? [] as $grade)
            <input type="hidden" name="grades[]" value="{{ $grade }}">
        @endforeach
        <h2 class="font-semibold">Save audience</h2>
        <x-admin.input name="name" label="Name" required placeholder="Gold customers without Plus" />
        <x-admin.textarea name="description" label="Notes" rows="2" />
        <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save audience</button>
    </form>

    <div class="hidden md:block overflow-x-auto rounded-2xl bg-white ring-1 ring-brand/10">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Estimate</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($audiences as $audience)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-semibold">{{ $audience->name }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Support\MoneyFormat::compact($audience->estimated_count) }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="post" action="{{ route('admin.growth.audiences.destroy', $audience) }}" onsubmit="event.preventDefault(); confirmForm(this, { title: 'Remove audience?', message: 'This does not change customers.' })">
                                @csrf @method('DELETE')
                                <button class="text-red-600 text-xs font-semibold">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No saved audiences yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="md:hidden space-y-3">
        @forelse ($audiences as $audience)
            <article class="rounded-2xl bg-white ring-1 ring-gray-200 p-4">
                <p class="font-semibold">{{ $audience->name }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ \App\Support\MoneyFormat::compact($audience->estimated_count) }} people</p>
                <form method="post" action="{{ route('admin.growth.audiences.destroy', $audience) }}" class="mt-3" onsubmit="event.preventDefault(); confirmForm(this, { title: 'Remove audience?', message: 'This does not change customers.' })">
                    @csrf @method('DELETE')
                    <button class="text-red-600 text-xs font-semibold">Remove</button>
                </form>
            </article>
        @empty
            <p class="text-sm text-gray-500">No saved audiences yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $audiences->links() }}</div>
</x-admin.layout>
