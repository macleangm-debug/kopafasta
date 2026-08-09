{{-- Create PIN + enroll recovery answers (system picks 3 random questions) --}}
<x-site.layout :auth="true" :title="brand_title(__('site.auth.pin_recovery.setup_title'))">
    @php
        $needsPin = $needsPin ?? true;
        $questions = $questions ?? [];
    @endphp
    <section class="min-h-full grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.auth.pin_recovery.setup_eyebrow') }}</p>
                <h2 class="mt-2 text-4xl font-bold tracking-tight leading-tight">{{ __('site.auth.pin_recovery.setup_aside_title') }}</h2>
                <p class="mt-4 text-white/70 max-w-md">{{ __('site.auth.pin_recovery.setup_aside_body') }}</p>
            </div>
            <p class="relative text-xs text-white/50">© {{ date('Y') }} {{ brand_name() }}</p>
        </aside>

        <div class="flex items-center justify-center px-4 py-10 sm:px-12">
            <div class="w-full max-w-md">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-block mb-6"><x-site.brand-mark size="md" /></a>
                <div class="glass-card p-8 sm:p-10">
                    @if (session('status'))
                        <div class="mb-5 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
                    @endif

                    <h1 class="text-2xl font-bold text-gray-900">
                        {{ $needsPin ? __('site.auth.pin_recovery.setup_title') : __('site.auth.pin_recovery.recovery_only_title') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ $needsPin ? __('site.auth.pin_recovery.setup_body') : __('site.auth.pin_recovery.recovery_only_body') }}
                    </p>

                    <div class="mt-5 rounded-xl bg-brand-muted/60 ring-1 ring-brand/10 px-4 py-3 text-sm text-brand">
                        <p class="font-semibold">{{ __('site.auth.pin_recovery.enroll_notice_title') }}</p>
                        <p class="mt-1 text-brand/90">{{ __('site.auth.pin_recovery.enroll_notice_body') }}</p>
                    </div>

                    <form method="POST" action="{{ route('site.borrower.setup-pin.post') }}" class="mt-6 space-y-4" autocomplete="off">
                        @csrf

                        @if ($needsPin)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.pin_label') }}</label>
                                <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-center text-lg tracking-[0.5em] font-mono outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                                @error('pin')
                                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.pin_recovery.confirm_pin') }}</label>
                                <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                                       class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-center text-lg tracking-[0.5em] font-mono outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                            </div>
                        @endif

                        <div class="pt-2 border-t border-gray-100 space-y-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('site.auth.pin_recovery.enroll_questions_label') }}</p>
                            @foreach ($questions as $index => $question)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                        <span class="text-xs font-semibold text-brand mr-1">{{ $index + 1 }}.</span>
                                        {{ $question['prompt'] }}
                                    </label>
                                    <input
                                        type="text"
                                        name="answers[{{ $question['key'] }}]"
                                        value="{{ old('answers.'.$question['key']) }}"
                                        required
                                        autocomplete="off"
                                        @if (($question['input'] ?? '') === 'digits')
                                            inputmode="numeric"
                                            maxlength="{{ $question['digits'] ?? 4 }}"
                                            pattern="{{ '\\d{'.($question['digits'] ?? 4).'}' }}"
                                        @endif
                                        class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                                    >
                                    @error('answers.'.$question['key'])
                                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                        <p class="text-xs text-gray-500">{{ __('site.auth.pin_recovery.setup_hint') }}</p>
                        <button class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold py-3.5 rounded-xl text-sm shadow-sm">
                            {{ $needsPin ? __('site.auth.pin_recovery.setup_cta') : __('site.auth.pin_recovery.recovery_only_cta') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
