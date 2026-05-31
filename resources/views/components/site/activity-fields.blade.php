@props([
    'activityType' => '',
    'activityDetails' => [],
    'incomeRange' => '',
    'prefix' => '',
])

@php
    $types = config('activity_profiles.types');
    $fields = config('activity_profiles.fields');
    $incomeRanges = config('income_ranges');
    $details = old('activity_details', $activityDetails ?? []);
@endphp

<div x-data="activityForm(@js($fields), @js($details), @js(old('activity_type', $activityType)))">
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">What do you do? <span class="text-red-500">*</span></label>
            <select name="activity_type" x-model="activityType" @change="onTypeChange()" required
                    class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                <option value="">Select activity</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected(old('activity_type', $activityType) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <template x-for="field in activeFields" :key="field.key">
            <div :class="field.type === 'select' ? '' : 'sm:col-span-2'">
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    <span x-text="field.label"></span>
                    <span x-show="field.required" class="text-red-500">*</span>
                </label>
                <template x-if="field.type === 'select'">
                    <select :name="'activity_details[' + field.key + ']'" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="field.required">
                        <option value="">Select range</option>
                        @foreach ($incomeRanges as $key => $range)
                            <option value="{{ $key }}">{{ $range['label'] }}</option>
                        @endforeach
                    </select>
                </template>
                <template x-if="field.type !== 'select'">
                    <input type="text"
                           :name="'activity_details[' + field.key + ']'"
                           :value="details[field.key] || ''"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                           :required="field.required">
                </template>
            </div>
        </template>

        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Monthly income range <span class="text-red-500">*</span></label>
            <select name="income_range" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                <option value="">Select income range</option>
                @foreach ($incomeRanges as $key => $range)
                    <option value="{{ $key }}" @selected(old('income_range', $incomeRange) === $key)>{{ $range['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function activityForm(fieldMap, initialDetails, initialType) {
            return {
                fieldMap,
                details: initialDetails || {},
                activityType: initialType || '',
                activeFields: [],
                init() {
                    this.refreshFields();
                },
                onTypeChange() {
                    this.refreshFields();
                },
                refreshFields() {
                    this.activeFields = this.fieldMap[this.activityType] || [];
                },
            };
        }
    </script>
    @endpush
@endonce
