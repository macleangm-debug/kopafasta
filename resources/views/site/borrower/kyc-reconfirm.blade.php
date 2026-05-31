<x-site.borrower-layout title="Reconfirm KYC — Kopafasta" active="profile">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">Reconfirm your details</h1>
        <p class="text-sm text-gray-500 mb-6">Your residence and activity information is older than the configured KYC freshness period. Please confirm or update it to continue applying for loans.</p>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('site.borrower.kyc-reconfirm.update') }}" class="space-y-6">
            @csrf @method('PUT')

            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-4">Residence information</h2>
                <div class="grid sm:grid-cols-2 gap-4" x-data="tzAddress(@js(config('tanzania_locations')), @js(old('region', $customer->region)), @js(old('district', $customer->district)))" x-init="init()">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Region</label>
                        <select name="region" x-model="region" @change="onRegionChange()" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                            <option value="">Select region</option>
                            <template x-for="(districts, name) in locations" :key="name"><option :value="name" x-text="name"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">District</label>
                        <select name="district" x-model="district" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                            <option value="">Select district</option>
                            <template x-for="d in districtOptions" :key="d"><option :value="d" x-text="d"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Ward (optional)</label>
                        <input name="ward" value="{{ old('ward', $customer->ward) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Street</label>
                        <input name="street" value="{{ old('street', $customer->street ?? $customer->address) }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-4">Activity information</h2>
                <div x-data="activityForm(@js(config('activity_profiles.fields')), @js(old('activity_details', $customer->activity_details ?? [])), @js(old('activity_type', $customer->activity_type ?? $customer->employment_type)))" x-init="init()">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-gray-600 mb-1">What do you do?</label>
                            <select name="activity_type" x-model="activityType" @change="onTypeChange()" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                                <option value="">Select activity</option>
                                @foreach (config('activity_profiles.types') as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <template x-for="field in activeFields" :key="field.key">
                            <div :class="field.type === 'select' ? '' : 'sm:col-span-2'">
                                <label class="block text-xs text-gray-600 mb-1" x-text="field.label"></label>
                                <template x-if="field.type === 'select'">
                                    <select :name="'activity_details[' + field.key + ']'" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm" :required="field.required">
                                        <option value="">Select</option>
                                        @foreach (config('income_ranges') as $key => $range)
                                            <option value="{{ $key }}">{{ $range['label'] }}</option>
                                        @endforeach
                                    </select>
                                </template>
                                <template x-if="field.type !== 'select'">
                                    <input type="text" :name="'activity_details[' + field.key + ']'" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm" :required="field.required">
                                </template>
                            </div>
                        </template>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-gray-600 mb-1">Monthly income range</label>
                            <select name="income_range" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                                <option value="">Select income range</option>
                                @foreach (config('income_ranges') as $key => $range)
                                    <option value="{{ $key }}" @selected(old('income_range', $customer->income_range) === $key)>{{ $range['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                Confirm and save
            </button>
        </form>
    </div>

    @push('scripts')
        <script>
            function tzAddress(locations, initialRegion, initialDistrict) {
                return {
                    locations, region: initialRegion || '', district: initialDistrict || '', districtOptions: [],
                    init() { this.refreshDistricts(); },
                    onRegionChange() { this.district = ''; this.refreshDistricts(); },
                    refreshDistricts() { this.districtOptions = this.region && this.locations[this.region] ? this.locations[this.region] : []; },
                };
            }
            function activityForm(fieldMap, initialDetails, initialType) {
                return {
                    fieldMap, details: initialDetails || {}, activityType: initialType || '', activeFields: [],
                    init() { this.refreshFields(); },
                    onTypeChange() { this.refreshFields(); },
                    refreshFields() { this.activeFields = this.fieldMap[this.activityType] || []; },
                };
            }
        </script>
    @endpush
    @stack('scripts')
</x-site.borrower-layout>
