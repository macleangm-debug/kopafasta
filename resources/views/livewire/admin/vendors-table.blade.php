<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="{{ ($affiliateMode ?? false) ? 'Search affiliate partner name, code, phone…' : 'Search partner name, code, phone…' }}">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="vendor_number" :label="($affiliateMode ?? false) ? 'Partner #' : 'Partner #'" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name"          label="Name" />
        @if ($affiliateMode ?? false)
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Affiliate code</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Lifecycle</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Risk</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">KPI</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Commission</th>
            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Tracking link</th>
        @else
            <x-admin.th :sort="$sort" :direction="$direction" col="category"      label="Category" />
        @endif
        <x-admin.th :sort="$sort" :direction="$direction" col="phone"         label="Phone" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"        label="Status" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Open tasks</th>
        <th class="px-5 py-3"></th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $r->vendor_number }}</td>
                <td class="px-5 py-3 font-medium">
                    <a href="{{ route('admin.partners.show', $r) }}" class="text-brand hover:underline">{{ $r->name }}</a>
                </td>
                @if ($affiliateMode ?? false)
                    <td class="px-5 py-3 font-mono text-xs">{{ $r->affiliate_code ?: '—' }}</td>
                    <td class="px-5 py-3 text-xs text-gray-700">
                        {{ app(\App\Services\AffiliateLifecycleService::class)->label(app(\App\Services\AffiliateLifecycleService::class)->statusFor($r)) }}
                    </td>
                    <td class="px-5 py-3 text-xs capitalize font-semibold">{{ $r->affiliate_risk_flag ?? 'low' }}</td>
                    <td class="px-5 py-3 text-sm">
                        {{ number_format((float) (($r->affiliate_evaluation_snapshot ?? [])['kpi_score'] ?? 0), 1) }}
                    </td>
                    <td class="px-5 py-3 text-sm">{{ format_number((float) ($r->affiliate_commission_percent ?? config('affiliates.default_commission_percent', 0)), 1) }}%</td>
                    <td class="px-5 py-3 text-xs text-gray-600 max-w-[14rem] truncate">
                        @if ($r->affiliate_code)
                            {{ app(\App\Services\AffiliateService::class)->affiliateLink($r) }}
                        @else
                            —
                        @endif
                    </td>
                @else
                    <td class="px-5 py-3 text-gray-600">{{ display_label((string) $r->category, 'vendor_category') }}</td>
                @endif
                <td class="px-5 py-3 text-gray-600">{{ $r->phone }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'active'    => 'bg-emerald-100 text-emerald-800',
                        'inactive'  => 'bg-amber-100 text-amber-800',
                        'suspended' => 'bg-red-100 text-red-800',
                    ]" />
                </td>
                <td class="px-5 py-3">
                    @if ((int) ($r->open_tasks_count ?? 0) > 0)
                        <a href="{{ route('admin.partners.show', $r) }}" class="inline-flex text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-900">
                            {{ $r->open_tasks_count }} open
                        </a>
                    @else
                        <span class="text-xs text-gray-400">None</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right text-sm">
                    <a href="{{ route('admin.partners.edit', $r) }}" class="text-gray-600 hover:text-brand">Edit</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ ($affiliateMode ?? false) ? 12 : 7 }}" class="px-5 py-12 text-center text-gray-500">{{ ($affiliateMode ?? false) ? 'No affiliate partners found.' : 'No partners found.' }}</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>
