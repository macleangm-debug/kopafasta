<x-admin.layout title="Origination auto-assignment" heading="Origination auto-assignment" subheading="Valuer, GPS, and insurance partners — assign by region after the borrower pays">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">
            Screening requests the job; the borrower (group leader on group loans) pays first.
            After payment the system picks an active partner who covers the borrower region.
            Recovery auto-assign stays under Settings → Recovery policy.
        </p>
        <a href="{{ route('admin.partners.index') }}" class="text-sm font-semibold text-brand hover:underline">← Partners hub</a>
    </div>

    <form method="POST" action="{{ route('admin.partners.origination-auto-assign.save') }}" class="space-y-5">
        @csrf
        @foreach ($boards as $board)
            @php $settings = $board['settings'] ?? []; @endphp
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest font-bold text-brand">Origination</p>
                        <h3 class="text-base font-bold text-gray-900 mt-0.5">{{ $board['label'] }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ $board['kpi_source'] }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold',
                            'bg-emerald-100 text-emerald-800' => $settings['enabled'] ?? false,
                            'bg-gray-100 text-gray-600' => ! ($settings['enabled'] ?? false),
                        ])>
                            {{ ($settings['enabled'] ?? false) ? 'Auto-assign on' : 'Auto-assign off' }}
                        </span>
                        <a href="{{ $board['create_url'] }}"
                           class="inline-flex items-center rounded-full bg-white ring-1 ring-gray-200 px-2.5 py-1 text-[11px] font-semibold text-brand hover:bg-brand-muted/40">
                            {{ $board['partner_count'] }} partner{{ $board['partner_count'] === 1 ? '' : 's' }} · manage →
                        </a>
                    </div>
                </div>
                <div class="p-5">
                    <x-admin.auto-assign-settings
                        :suffix="$board['suffix']"
                        :settings="$settings"
                        :show-sla-days="$board['show_sla_days']" />
                </div>
            </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-lg shadow-sm">
                Save origination auto-assign
            </button>
        </div>
    </form>
</x-admin.layout>
