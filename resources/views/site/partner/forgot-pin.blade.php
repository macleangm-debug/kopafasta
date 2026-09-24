<x-site.layout :auth="true" :title="brand_title(__('site.auth.partner_forgot_title'))">
    <section class="min-h-[calc(100dvh-4rem)] md:min-h-[calc(100dvh-6.5rem)] grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.auth.partner_portal') }}</p>
                <h2 class="text-4xl font-bold tracking-tight leading-tight">{{ __('site.auth.partner_forgot_title') }}</h2>
                <p class="mt-4 text-white/70 max-w-md">{{ __('site.auth.partner_forgot_intro') }}</p>
            </div>
            <p class="relative text-xs text-white/50">© {{ date('Y') }} {{ brand_name() }}</p>
        </aside>
        <div class="flex items-center justify-center px-4 py-10 sm:px-12">
            <div class="w-full max-w-md">
                <div class="glass-card p-8 sm:p-10">
                    @if (session('feedback.message'))
                        <div class="mb-5 rounded-xl px-4 py-3 text-sm {{ (session('feedback.tone') ?? 'error') === 'success' ? 'bg-emerald-50 ring-1 ring-emerald-200 text-emerald-900' : 'bg-red-50 ring-1 ring-red-200 text-red-800' }}">
                            {{ session('feedback.message') }}
                        </div>
                    @endif
                    <h1 class="text-2xl font-bold text-gray-900">
                        @if ($step === 3)
                            {{ __('site.auth.pin_recovery.pin_step_title') }}
                        @elseif ($step === 2)
                            {{ __('site.auth.pin_recovery.questions_title') }}
                        @else
                            {{ __('site.auth.partner_forgot_title') }}
                        @endif
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        @if ($step === 3)
                            {{ __('site.auth.pin_recovery.pin_step_intro') }}
                        @elseif ($step === 2)
                            {{ __('site.auth.pin_recovery.kba_intro', ['count' => max(2, $requiredCorrect), 'total' => count($questions)]) }}
                        @else
                            {{ __('site.auth.partner_forgot_intro') }}
                        @endif
                    </p>

                    @if ($step === 1)
                        <form method="POST" action="{{ route('site.partner.forgot-pin.start') }}" class="mt-6 space-y-5" data-no-draft>
                            @csrf
                            <x-site.turnstile action="forgot-pin" />
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.partner_account_number') }}</label>
                                <input name="partner_code" value="{{ $prefillCode }}" required autocomplete="off"
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-sm font-mono uppercase outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                                @error('partner_code')
                                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <x-site.phone-input name="phone" :label="__('site.feedback.phone')" :value="$prefillPhone" variant="rounded" :required="true" />
                            <button class="w-full bg-brand hover:bg-brand-light text-white font-bold py-3.5 rounded-xl text-sm">
                                {{ __('site.auth.pin_recovery.continue') }}
                            </button>
                        </form>
                    @elseif ($step === 2)
                        <form method="POST" action="{{ route('site.partner.forgot-pin.verify') }}" class="mt-6 space-y-5" data-no-draft>
                            @csrf
                            <input type="hidden" name="token" value="{{ $challengeToken }}">
                            @foreach ($questions as $question)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ $question['prompt'] }}</label>
                                    <input type="text" name="answers[{{ $question['key'] }}]" required autocomplete="off"
                                           class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                                </div>
                            @endforeach
                            <button class="w-full bg-brand hover:bg-brand-light text-white font-bold py-3.5 rounded-xl text-sm">
                                {{ __('site.auth.pin_recovery.verify_cta') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('site.partner.forgot-pin.reset') }}" class="mt-6 space-y-5" autocomplete="off" data-no-draft>
                            @csrf
                            <input type="hidden" name="token" value="{{ $challengeToken }}">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.pin_recovery.new_pin') }}</label>
                                <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-center text-lg tracking-[0.5em] font-mono">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.pin_recovery.confirm_pin') }}</label>
                                <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-center text-lg tracking-[0.5em] font-mono">
                            </div>
                            <button class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold py-3.5 rounded-xl text-sm">
                                {{ __('site.auth.pin_recovery.save_pin_cta') }}
                            </button>
                        </form>
                    @endif

                    <p class="mt-6 text-center text-xs text-gray-500">
                        <a href="{{ route('site.login', ['portal' => 'partner']) }}" class="font-semibold text-brand hover:underline">← {{ __('site.auth.pin_recovery.back_login') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
