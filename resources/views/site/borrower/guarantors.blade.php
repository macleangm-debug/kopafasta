<x-site.borrower-layout :title="brand_title(__('borrower.guarantors_page.title'))" active="guarantors" content-width="wide">

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.guarantors_page.title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ __('borrower.guarantors_page.subtitle') }}</p>

    <div class="grid lg:grid-cols-3 gap-6">

        <form method="POST" action="{{ route('site.borrower.guarantors.store') }}"
              class="lg:col-span-1 bg-white rounded-2xl border border-gray-200 p-6"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantors_page.confirm_title')), message: @js(__('borrower.guarantors_page.confirm_message')), confirmLabel: @js(__('borrower.guarantors_page.confirm_label')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf
            <h2 class="font-semibold mb-4">{{ __('borrower.guarantors_page.add_title') }}</h2>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.first_name') }}</label>
                    <input name="first_name" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('borrower.profile.fields.last_name') }}</label>
                    <input name="last_name" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mt-3">
                <x-site.phone-input
                    name="phone"
                    :label="__('borrower.profile.fields.phone')"
                    :value="old('phone')"
                    :locked-country="auth()->user()?->customer?->country_code ?? 'TZ'"
                    :required="true"
                />
            </div>

            <label class="block text-xs text-gray-600 mb-1 mt-3">{{ __('borrower.guarantors_page.email_optional') }}</label>
            <input type="email" name="email" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">

            <label class="block text-xs text-gray-600 mb-1 mt-3">{{ __('borrower.profile.fields.national_id') }}</label>
            <x-site.nida-input name="national_id" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm" />

            <label class="block text-xs text-gray-600 mb-1 mt-3">{{ __('borrower.guarantors_page.address_optional') }}</label>
            <input name="address" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">

            <label class="block text-xs text-gray-600 mb-1 mt-3">{{ __('borrower.profile.fields.relationship') }}</label>
            <x-site.profile-select
                name="relationship"
                :options="__('borrower.profile.guarantor_relationship_options')"
                :value="old('relationship')"
                :required="true"
                :placeholder="__('borrower.profile.select')"
            />

            <button class="mt-5 w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.guarantors_page.send_request') }}</button>
        </form>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('borrower.guarantors_page.linked_title') }}</h2>
                <span class="text-xs text-gray-500">{{ $links->count() }}</span>
            </div>
            @if ($links->isEmpty())
                <div class="p-10 text-center text-sm text-gray-500">{{ __('borrower.guarantors_page.empty') }}</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($links as $link)
                        @php
                            $g = $link->guarantor;
                            $color = match ($link->status) {
                                'accepted','verified' => 'bg-emerald-100 text-emerald-700',
                                'rejected'            => 'bg-red-100 text-red-700',
                                default               => 'bg-amber-100 text-amber-700',
                            };
                            $statusKey = __('borrower.guarantors_page.statuses.'.$link->status, [], null) !== 'borrower.guarantors_page.statuses.'.$link->status
                                ? __('borrower.guarantors_page.statuses.'.$link->status)
                                : ucfirst($link->status);
                        @endphp
                        <li class="px-5 py-4 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-sm">{{ $g ? $g->first_name.' '.$g->last_name : __('borrower.guarantors_page.unknown') }}</p>
                                <p class="text-xs text-gray-500">{{ $g->phone ?? '—' }} · {{ $g->relationship ?? '—' }}</p>
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $color }}">{{ $statusKey }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

</x-site.borrower-layout>
