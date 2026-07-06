<x-admin.layout title="Loyalty points" heading="Loyalty points" subheading="Earn points for actions, redeem for benefits">
    @include('admin.settings.engagement._nav', ['active' => 'loyalty-points'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    @php
        $actions = $values['actions'] ?? config('gamification.loyalty_points.actions', []);
        $options = $values['redemption_options'] ?? config('gamification.loyalty_points.redemption_options', []);
    @endphp

    <form method="POST" action="{{ route('admin.settings.engagement.loyalty-points.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Point values</h3>
            @foreach ($actions as $key => $action)
                <div class="grid md:grid-cols-2 gap-3">
                    <x-admin.input name="actions[{{ $key }}][label]" label="{{ $key }}" :value="$action['label'] ?? ''" />
                    <x-admin.input name="actions[{{ $key }}][points]" label="Points" type="number" :value="$action['points'] ?? 0" />
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Redemption catalog</h3>
            @foreach ($options as $i => $option)
                <div class="grid md:grid-cols-3 gap-3 pb-4 border-b border-gray-100 last:border-0">
                    <input type="hidden" name="redemption_options[{{ $i }}][key]" value="{{ $option['key'] ?? '' }}">
                    <x-admin.input name="redemption_options[{{ $i }}][label]" label="Label (EN)" :value="$option['label'] ?? ''" />
                    <x-admin.input name="redemption_options[{{ $i }}][label_sw]" label="Label (SW)" :value="$option['label_sw'] ?? ''" />
                    <x-admin.input name="redemption_options[{{ $i }}][points]" label="Points cost" type="number" :value="$option['points'] ?? 0" />
                    <x-admin.input name="redemption_options[{{ $i }}][benefit_type]" label="Benefit type" :value="$option['benefit_type'] ?? 'percent_discount'" />
                    <x-admin.input name="redemption_options[{{ $i }}][benefit_value]" label="Benefit value" type="number" step="0.001" :value="$option['benefit_value'] ?? 0" />
                    <x-admin.input name="redemption_options[{{ $i }}][fee_type]" label="Fee type (optional)" :value="$option['fee_type'] ?? ''" />
                    <x-admin.input name="redemption_options[{{ $i }}][expires_days]" label="Expires after (days)" type="number" :value="$option['expires_days'] ?? 90" />
                    <div class="md:col-span-3">
                        <x-admin.textarea name="redemption_options[{{ $i }}][description]" label="Description (EN)" rows="2" :value="$option['description'] ?? ''" />
                    </div>
                    <div class="md:col-span-3">
                        <x-admin.textarea name="redemption_options[{{ $i }}][description_sw]" label="Description (SW)" rows="2" :value="$option['description_sw'] ?? ''" />
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg">Save</button>
        </div>
    </form>
</x-admin.layout>
