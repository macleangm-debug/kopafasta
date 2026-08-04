<x-site.borrower-layout :title="brand_title(__('borrower.guarantor_notifications.title'))" active="guarantor-notifications" portalMode="guarantor" content-width="wide">

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold mb-1">{{ __('borrower.guarantor_notifications.title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('borrower.guarantor_notifications.subtitle') }}</p>
        </div>
        @if ($items->total() > 0)
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('site.borrower.guarantor-notifications.read') }}">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-full ring-1 ring-gray-200 bg-white hover:bg-gray-50">{{ __('borrower.guarantor_notifications.mark_all_read') }}</button>
                </form>
                <form method="POST" action="{{ route('site.borrower.guarantor-notifications.clear-all') }}"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor_notifications.clear_all_confirm_title')), message: @js(__('borrower.guarantor_notifications.clear_all_confirm_message')), confirmLabel: @js(__('borrower.guarantor_notifications.clear_all_confirm_label')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-full ring-1 ring-red-200 text-red-700 bg-red-50 hover:bg-red-100">{{ __('borrower.guarantor_notifications.clear_all') }}</button>
                </form>
            </div>
        @endif
    </div>

    @if ($items->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <p class="text-gray-500">{{ __('borrower.guarantor_notifications.empty') }}</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($items as $n)
                @php
                    $actionUrl = ($n->channel === 'in_app' && filled($n->recipient) && str_starts_with($n->recipient, '/'))
                        ? $n->recipient
                        : null;
                    if (method_exists($n, 'displayTitle')) {
                        $title = $n->displayTitle();
                        $body = $n->displayBody();
                    } else {
                        $lines = preg_split("/\r\n|\n|\r/", (string) ($n->message ?: '')) ?: [];
                        $title = $lines[0] ?? __('borrower.guarantor_notifications.fallback_title');
                        $body = trim(implode(' ', array_slice($lines, 1))) ?: ($n->message ?: $n->template);
                    }
                    $meta = is_array($n->meta) ? $n->meta : [];
                    $linkId = (int) ($meta['customer_guarantor_id'] ?? 0);
                    if ($linkId <= 0 && $n->template === 'guarantor_request' && $actionUrl) {
                        if (preg_match('#/guarantor-requests/(\d+)#', $actionUrl, $m)) {
                            $linkId = (int) $m[1];
                        }
                    }
                    $acceptUrl = ($n->template === 'guarantor_request' && $linkId > 0)
                        ? route('site.borrower.guarantor-requests.show', $linkId)
                        : null;
                    $declineUrl = ($n->template === 'guarantor_request' && $linkId > 0)
                        ? route('site.borrower.guarantor-requests.respond', $linkId)
                        : null;
                    $actionLabel = match ($n->template) {
                        'guarantor_request' => $acceptUrl
                            ? __('borrower.guarantor_notifications.accept_cta')
                            : __('borrower.guarantor_notifications.view_request'),
                        'guarantor_loan_arrears' => __('borrower.guarantor_notifications.view_loan'),
                        default => __('borrower.notifications.view_application'),
                    };
                @endphp
                <div class="bg-white rounded-2xl border border-gray-200 p-5 flex gap-4 {{ $n->read_at ? '' : 'ring-2 ring-amber-100' }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            @unless ($n->read_at)
                                <span class="text-[10px] font-bold uppercase tracking-widest text-amber-700">{{ __('borrower.guarantor_notifications.new_badge') }}</span>
                            @endunless
                            <span class="text-xs text-gray-400">{{ $n->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="font-semibold text-gray-900">{{ $title }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ $body }}</p>
                        @if ($acceptUrl && $declineUrl)
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ $acceptUrl }}"
                                   class="inline-flex items-center rounded-xl bg-brand-gold px-4 py-2 text-sm font-bold text-brand">
                                    {{ __('borrower.guarantor_notifications.accept_cta') }}
                                </a>
                                <form method="POST" action="{{ $declineUrl }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit"
                                            class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                                        {{ __('borrower.guarantor_notifications.decline_cta') }}
                                    </button>
                                </form>
                            </div>
                        @elseif ($actionUrl)
                            <a href="{{ url($actionUrl) }}" class="inline-flex mt-3 text-sm font-semibold text-amber-700 hover:text-amber-800">{{ $actionLabel }}</a>
                        @endif
                    </div>
                    @unless ($n->read_at)
                        <form method="POST" action="{{ route('site.borrower.notifications.item.read', $n) }}">
                            @csrf
                            <button class="text-xs font-semibold text-gray-500 hover:text-gray-700">{{ __('borrower.guarantor_notifications.mark_read') }}</button>
                        </form>
                    @endunless
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $items->links() }}</div>
    @endif
</x-site.borrower-layout>
