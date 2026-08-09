{{-- Forgot PIN — phone → questions → new PIN --}}
<x-site.layout :auth="true" :title="brand_title(__('site.auth.pin_recovery.title'))">
    @php
        $step = (int) ($step ?? old('step', 1));
        $questions = $questions ?? [];
        $requiredCorrect = (int) ($requiredCorrect ?? 2);
        $totalQuestions = max(count($questions), 2);
        $expiresAt = (int) ($expiresAt ?? 0);
    @endphp
    <section class="min-h-full grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold mb-2">{{ __('site.auth.pin_recovery.aside_eyebrow') }}</p>
                <h2 class="text-4xl font-bold tracking-tight leading-tight">{{ __('site.auth.pin_recovery.aside_title') }}</h2>
                <p class="mt-4 text-white/70 max-w-md">{{ __('site.auth.pin_recovery.aside_body') }}</p>
            </div>
            <p class="relative text-xs text-white/50">&copy; {{ date('Y') }} {{ brand('legal_name') }}</p>
        </aside>

        <div class="flex items-center justify-center px-4 py-8 sm:px-12">
            <div class="w-full max-w-md glass-card p-6 sm:p-10">
                <a href="{{ route('site.home') }}" class="lg:hidden mb-8 inline-block">
                    <x-site.brand-mark size="md" />
                </a>

                @if (in_array($step, [2, 3], true) && $expiresAt > 0)
                    <div
                        class="mb-5"
                        x-data="{
                            endsAt: {{ $expiresAt }} * 1000,
                            left: 0,
                            total: Math.max(1, {{ $expiresAt }} - Math.floor(Date.now() / 1000)),
                            tick() {
                                this.left = Math.max(0, Math.ceil((this.endsAt - Date.now()) / 1000));
                                if (this.left <= 0) {
                                    window.location = @js(route('site.forgot-pin'));
                                }
                            },
                            label() {
                                const m = Math.floor(this.left / 60);
                                const s = String(this.left % 60).padStart(2, '0');
                                return m + ':' + s;
                            },
                            init() {
                                this.tick();
                                setInterval(() => this.tick(), 250);
                            }
                        }"
                    >
                        <div class="flex items-center justify-between gap-3 text-xs font-semibold text-gray-600">
                            <span>{{ __('site.auth.pin_recovery.timer_label') }}</span>
                            <span class="tabular-nums text-brand" x-text="label()"></span>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand transition-[width] duration-200"
                                 :style="'width:' + Math.max(0, Math.min(100, (left / total) * 100)) + '%'"></div>
                        </div>
                    </div>
                @endif

                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    @if ($step === 3)
                        {{ __('site.auth.pin_recovery.pin_step_title') }}
                    @elseif ($step === 2)
                        {{ __('site.auth.pin_recovery.questions_title') }}
                    @else
                        {{ __('site.auth.pin_recovery.title') }}
                    @endif
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    @if ($step === 3)
                        {{ __('site.auth.pin_recovery.pin_step_intro') }}
                    @elseif ($step === 2)
                        {{ __('site.auth.pin_recovery.kba_intro', ['count' => max(2, $requiredCorrect), 'total' => $totalQuestions]) }}
                    @else
                        {{ __('site.auth.pin_recovery.intro') }}
                    @endif
                </p>

                @if ($step === 1)
                    <form method="POST" action="{{ route('site.forgot-pin.start') }}" class="mt-6 space-y-5">
                        @csrf
                        <x-site.phone-input name="phone" :label="__('site.feedback.phone')" :value="old('phone', $prefillPhone ?? null)" variant="rounded" :required="true" :show-errors="false" />
                        <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold py-3.5 transition">
                            {{ __('site.auth.pin_recovery.continue') }}
                        </button>
                    </form>
                @elseif ($step === 2)
                    <form method="POST" action="{{ route('site.forgot-pin.verify-challenge') }}" class="mt-6 space-y-5">
                        @csrf
                        <input type="hidden" name="token" value="{{ $challengeToken }}">
                        <input type="hidden" name="phone" value="{{ old('phone', $prefillPhone) }}">

                        <div class="space-y-4">
                            @foreach ($questions as $index => $question)
                                @php $field = 'answers.'.$question['key']; @endphp
                                <div>
                                    @if (($question['input'] ?? '') === 'date' || str_contains($question['key'], 'dob'))
                                        <x-site.date-input
                                            :name="'answers['.$question['key'].']'"
                                            :label="($index + 1).'. '.$question['prompt']"
                                            :value="old($field)"
                                            :required="true"
                                            min="1940-01-01"
                                            :max="now()->subYears(18)->format('Y-m-d')"
                                        />
                                    @else
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            <span class="text-xs font-semibold text-brand mr-1">{{ $index + 1 }}.</span>
                                            {{ $question['prompt'] }}
                                        </label>
                                        <input type="text"
                                               name="answers[{{ $question['key'] }}]"
                                               value="{{ old($field) }}"
                                               autocomplete="off"
                                               @if (($question['input'] ?? '') === 'digits')
                                                   inputmode="numeric"
                                                   maxlength="{{ $question['digits'] ?? 4 }}"
                                               @endif
                                               class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold py-3.5 transition">
                            {{ __('site.auth.pin_recovery.verify_cta') }}
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('site.forgot-pin.reset-challenge') }}" class="mt-6 space-y-5" autocomplete="off">
                        @csrf
                        <input type="hidden" name="token" value="{{ $challengeToken }}">
                        <input type="hidden" name="phone" value="{{ old('phone', $prefillPhone) }}">

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

                        <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold py-3.5 transition">
                            {{ __('site.auth.pin_recovery.save_pin_cta') }}
                        </button>
                    </form>
                @endif

                <p class="mt-6 text-sm text-gray-600">
                    <a href="{{ route('site.login', array_filter(['phone' => $prefillPhone ?? null])) }}" class="text-brand font-semibold hover:underline">
                        ← {{ __('site.auth.pin_recovery.back_login') }}
                    </a>
                </p>
            </div>
        </div>
    </section>
</x-site.layout>
