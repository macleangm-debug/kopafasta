{{-- Forgot PIN — enrolled security questions only (no SMS OTP) --}}
<x-site.layout :auth="true" :title="brand_title(__('site.auth.pin_recovery.title'))">
    <section class="min-h-full flex items-center justify-center premium-gradient px-4 py-12">
        <div class="w-full max-w-md glass-card p-6 sm:p-8">
            <a href="{{ route('site.home') }}" class="inline-block mb-6 lg:hidden">
                <x-site.brand-mark size="md" />
            </a>

            @php
                $step = (int) ($step ?? old('step', 1));
                $mode = (string) ($mode ?? old('mode', 'phone'));
                $questions = $questions ?? [];
                $requiredCorrect = (int) ($requiredCorrect ?? 2);
            @endphp

            <h1 class="text-2xl font-bold text-gray-900">{{ __('site.auth.pin_recovery.title') }}</h1>
            <p class="mt-2 text-sm text-gray-600">
                @if ($step === 1)
                    {{ __('site.auth.pin_recovery.intro') }}
                @else
                    {{ __('site.auth.pin_recovery.kba_intro', ['count' => max(2, $requiredCorrect), 'total' => max(count($questions), 2)]) }}
                @endif
            </p>

            @if (session('status'))
                <div class="mt-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mt-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @if ($step === 1)
                <form method="POST" action="{{ route('site.forgot-pin.start') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-site.phone-input name="phone" :label="__('site.feedback.phone')" :value="old('phone', $prefillPhone ?? null)" variant="rounded" :required="true" />
                    <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold py-3 transition">
                        {{ __('site.auth.pin_recovery.continue') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('site.forgot-pin.reset-challenge') }}" class="mt-6 space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $challengeToken }}">
                    <input type="hidden" name="phone" value="{{ old('phone', $prefillPhone) }}">

                    <div class="space-y-4">
                        @foreach ($questions as $index => $question)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    <span class="text-xs font-semibold text-brand mr-1">{{ $index + 1 }}.</span>
                                    {{ $question['prompt'] }}
                                </label>
                                <input type="text"
                                       name="answers[{{ $question['key'] }}]"
                                       value="{{ old('answers.'.$question['key']) }}"
                                       autocomplete="off"
                                       @if (($question['input'] ?? '') === 'digits')
                                           inputmode="numeric"
                                           maxlength="{{ $question['digits'] ?? 4 }}"
                                       @endif
                                       class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-2 border-t border-gray-100 space-y-4">
                        <p class="text-xs text-gray-500">{{ __('site.auth.pin_recovery.set_pin_hint') }}</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.pin_recovery.new_pin') }}</label>
                            <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-3 text-center text-lg tracking-[0.5em] font-mono outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.pin_recovery.confirm_pin') }}</label>
                            <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                                   class="w-full rounded-xl border border-gray-200 px-3 py-3 text-center text-lg tracking-[0.5em] font-mono outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                        </div>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold py-3 transition">
                        {{ __('site.auth.pin_recovery.reset_cta') }}
                    </button>
                </form>
            @endif

            <p class="mt-6 text-center text-sm text-gray-500">
                <a href="{{ route('site.login', array_filter(['phone' => $prefillPhone ?? null])) }}" class="text-brand font-semibold hover:underline">
                    ← {{ __('site.auth.pin_recovery.back_login') }}
                </a>
            </p>
        </div>
    </section>
</x-site.layout>
