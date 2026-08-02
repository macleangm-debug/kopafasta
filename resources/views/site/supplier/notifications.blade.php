<x-site.supplier-layout :title="brand_title(__('site.partner_portal.nav_notifications'))" active="notifications">
    <x-site.borrower-page-header
        :eyebrow="__('site.supplier_portal.title')"
        :title="__('site.partner_portal.nav_notifications')"
        :subtitle="__('site.partner_portal.no_notifications')"
    />

    <div class="glass-card rounded-2xl ring-1 ring-brand/10">
        @if ($notifications->isEmpty())
            <x-site.empty-state
                :title="__('site.partner_portal.no_notifications')"
                :description="__('site.partner_portal.no_notifications')"
            />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($notifications as $n)
                    <li class="px-5 py-4 flex items-start gap-3 {{ $n->read_at ? '' : 'bg-brand-muted/30' }}">
                        <span class="size-9 rounded-full bg-brand/10 text-brand grid place-items-center text-xs font-bold uppercase shrink-0">{{ substr($n->channel ?? 'N', 0, 1) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ $n->subject ?? __('site.partner_portal.nav_notifications') }}</p>
                            <p class="text-sm text-gray-600 mt-0.5">{{ $n->message ?? '' }}</p>
                            <p class="text-[11px] text-gray-400 mt-1 uppercase tracking-wide">{{ $n->channel }} · {{ $n->created_at?->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6">{{ $notifications->links() }}</div>
</x-site.supplier-layout>
