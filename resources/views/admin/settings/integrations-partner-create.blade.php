<x-admin.layout title="Add integration partner" heading="Add partner" subheading="Register a payment, messaging, or compliance partner">
    @include('admin.settings._tabs', ['active' => 'integrations'])

    <div class="mb-4">
        <a href="{{ route('admin.settings.integrations') }}" class="text-sm font-semibold text-brand hover:underline">← Integrations hub</a>
    </div>

    <form method="POST" action="{{ route('admin.settings.integrations.partners.store') }}"
          x-data="{ category: @js(old('category', 'payment')) }"
          class="bg-white rounded-2xl ring-1 ring-brand/10 shadow-sm p-6 max-w-2xl space-y-5">
        @csrf
        <x-admin.input name="label" label="Partner name" placeholder="e.g. Selcom, Beem, Flutterwave" required />
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Category</label>
            <select name="category" x-model="category" class="w-full text-sm border border-brand/15 rounded-xl px-3.5 py-2.5" required>
                @foreach ($categories as $key => $meta)
                    <option value="{{ $key }}">{{ $meta['label'] ?? $key }}</option>
                @endforeach
            </select>
        </div>
        <x-admin.input name="description" label="Description (optional)" placeholder="Short note for admins" />
        <x-admin.input name="docs_url" label="Docs URL (optional)" placeholder="https://…" />

        <div x-show="category === 'payment'" x-cloak class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 space-y-3">
            <p class="text-sm font-semibold text-gray-800">Supported rails</p>
            <p class="text-xs text-gray-500">Payment partners can offer mobile money, bank transfer, or both.</p>
            <div class="flex flex-wrap gap-3">
                @foreach ($channelOptions as $key => $label)
                    <label class="inline-flex items-center gap-2 rounded-lg bg-white ring-1 ring-gray-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="channels[]" value="{{ $key }}"
                               @checked($key === 'mobile_money' || in_array($key, old('channels', ['mobile_money']), true))
                               class="size-4 rounded border-gray-300 text-brand">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.settings.integrations') }}" class="rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700">Cancel</a>
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl">Add partner</button>
        </div>
    </form>
</x-admin.layout>
