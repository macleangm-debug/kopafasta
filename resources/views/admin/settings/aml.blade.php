<x-admin.layout title="AML Settings" heading="AML Thresholds" subheading="Transaction monitoring & FIU reporting">
    @include('admin.settings._tabs', ['active' => 'aml'])
    <x-admin.settings-editor
        action="{{ route('admin.settings.aml.save') }}"
        submit-label="Save AML settings"
    >
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="large_txn_threshold_tzs" label="Large txn threshold (TZS)" money :decimals="2" :value="$values['large_txn_threshold_tzs'] ?? '20000000'" required />
                <x-admin.input name="large_txn_threshold_usd" label="Large txn threshold (USD)" type="number" step="0.01" :value="$values['large_txn_threshold_usd'] ?? '10000'" required />
                <x-admin.input name="velocity_threshold_count" label="Velocity threshold (count)" type="number" :value="$values['velocity_threshold_count'] ?? '10'" required />
                <x-admin.input name="velocity_window_days" label="Velocity window (days)" type="number" :value="$values['velocity_window_days'] ?? '7'" required />
                <x-admin.input name="fiu_email" label="FIU report email" type="email" :value="$values['fiu_email'] ?? ''" />
                <x-admin.select name="mlro_user_id" label="MLRO (compliance officer)" :options="$users" :value="$values['mlro_user_id'] ?? null" placeholder="—" />
            </div>

            <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                <input type="hidden" name="auto_report_to_fiu" value="0">
                <input type="checkbox" name="auto_report_to_fiu" value="1" @checked(!empty($values['auto_report_to_fiu'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span class="text-gray-800">Auto-report critical STRs to FIU</span>
            </label>
        </div>
    </x-admin.settings-editor>
</x-admin.layout>
