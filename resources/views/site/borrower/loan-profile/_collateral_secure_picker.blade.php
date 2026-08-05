@props([
    'assetCards',
    'typeIcons',
    'formAction',
    'confirmTitle',
    'confirmBody',
])

@if ($assetCards->isNotEmpty())
    <div class="pt-2">
        <p class="text-[11px] uppercase tracking-widest text-gray-500 font-bold mb-3">{{ __('borrower.collateral_secure.select_saved') }}</p>
        <div class="grid sm:grid-cols-2 gap-3" x-data="{ selected: null }">
            @foreach ($assetCards as $card)
                <button type="button"
                        @click="selected = {{ (int) $card['id'] }}"
                        class="text-left rounded-2xl overflow-hidden ring-2 transition bg-white"
                        :class="selected === {{ (int) $card['id'] }} ? 'ring-brand shadow-md' : 'ring-gray-200 hover:ring-brand/40'">
                    <div class="relative h-28 bg-gradient-to-br from-brand-muted/50 to-white">
                        @if (! empty($card['thumbnail']))
                            <img src="{{ $card['thumbnail'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                        @else
                            <span class="absolute inset-0 grid place-items-center text-4xl">{{ $typeIcons[$card['asset_type'] ?? ''] ?? '📦' }}</span>
                        @endif
                        <span class="absolute top-2 left-2 text-[10px] font-bold bg-white/95 px-2 py-1 rounded-full text-gray-800">
                            {{ $typeIcons[$card['asset_type'] ?? ''] ?? '' }} {{ $card['type_label'] ?? '' }}
                        </span>
                    </div>
                    <div class="p-3">
                        <p class="font-extrabold text-gray-900 truncate">{{ $card['label'] }}</p>
                        @if (! empty($card['registration_number']))
                            <p class="text-xs font-semibold text-gray-600 mt-1">{{ $card['registration_number'] }}</p>
                        @endif
                        @if (! empty($card['insurance_expires_at']))
                            <p class="text-xs font-semibold text-gray-500 mt-1 tabular-nums">
                                {{ __('borrower.collateral_secure.insurance_expires') }}: {{ $card['insurance_expires_at'] }}
                            </p>
                        @endif
                    </div>
                </button>
            @endforeach

            <form method="POST" action="{{ $formAction }}" class="sm:col-span-2"
                  @submit.prevent="
                      if (!selected) { return; }
                      $el.querySelector('[name=customer_asset_id]').value = selected;
                      window.confirmForm($el, {
                          title: @js($confirmTitle),
                          message: @js($confirmBody),
                          confirmLabel: @js(__('borrower.collateral_secure.use_this')),
                          confirmClass: 'bg-brand hover:bg-brand-light text-white font-extrabold',
                          tone: 'confirm'
                      });
                  ">
                @csrf
                <input type="hidden" name="customer_asset_id" value="">
                <button type="submit"
                        :disabled="!selected"
                        class="inline-flex font-extrabold px-6 py-3 rounded-xl text-sm bg-brand text-white hover:bg-brand-light disabled:opacity-40 disabled:cursor-not-allowed shadow-sm">
                    {{ __('borrower.collateral_secure.use_this') }}
                </button>
            </form>
        </div>
    </div>
@endif
