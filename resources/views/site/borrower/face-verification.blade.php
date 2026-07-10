<x-site.borrower-layout :title="brand_title(__('borrower.nida.face_title'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.nida.face_title'),
            'subtitle' => __('borrower.nida.face_capture_hint'),
            'customer' => $customer,
            'active' => 'personal',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'face',
        ])

        @if (session('status'))
            <div class="mb-5 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-5 rounded-2xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @php
            $faceStatus = $customer->face_verification_status ?? 'incomplete';
            $doneCount = collect($steps ?? [])->where('done', true)->count();
            $totalSteps = max(1, count($steps ?? []));
        @endphp

        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20 mb-6">
            <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
            <div class="relative px-5 sm:px-8 py-7 sm:py-8">
                <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ __('borrower.nida.face_title') }}</p>
                <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight">
                    @if ($faceStatus === 'verified')
                        {{ __('borrower.face_verification_page.hero_verified') }}
                    @elseif ($faceStatus === 'pending')
                        {{ __('borrower.face_verification_page.hero_pending') }}
                    @elseif ($faceStatus === 'rejected')
                        {{ __('borrower.face_verification_page.hero_rejected') }}
                    @else
                        {{ __('borrower.face_verification_page.hero_start') }}
                    @endif
                </h1>
                <p class="mt-2 text-sm text-white/75 max-w-xl">{{ __('borrower.nida.face_capture_hint') }}</p>

                @if (! in_array($faceStatus, ['verified', 'pending'], true))
                    <div class="mt-6 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.face_verification_page.progress_label') }}</p>
                            <p class="mt-1 text-xl font-extrabold tabular-nums">
                                {{ $doneCount }}<span class="text-white/60 text-base font-semibold"> / {{ $totalSteps }}</span>
                            </p>
                        </div>
                        <div class="flex-1 max-w-xs h-1.5 rounded-full bg-white/20 overflow-hidden">
                            <div class="h-full rounded-full bg-amber-300 transition-all" style="width: {{ min(100, (int) round(($doneCount / $totalSteps) * 100)) }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if ($faceStatus === 'rejected' && $customer->face_rejection_notes)
            <div class="mb-6 rounded-2xl bg-red-50 ring-1 ring-red-200 px-5 py-4 text-sm text-red-800">
                <p class="font-semibold">{{ __('borrower.face_verification_page.rejected_title') }}</p>
                <p class="mt-1">{{ $customer->face_rejection_notes }}</p>
                <p class="mt-2 text-xs">{{ __('borrower.face_verification_page.rejected_hint') }}</p>
            </div>
        @endif

        @if ($faceStatus === 'verified')
            <x-site.face-verification-status :customer="$customer" :photos="$photos" :angles="$angles" />
            <div class="mt-6 text-center rounded-3xl bg-gradient-to-b from-emerald-50/80 to-white ring-1 ring-emerald-200/80 px-6 py-8">
                <p class="text-sm text-emerald-800 font-semibold">{{ __('borrower.face_verification_page.approved_hint') }}</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    @if (! empty($returnUrl))
                        <a href="{{ $returnUrl }}" class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-full text-sm shadow-sm">
                            {{ __('borrower.apply.kyc_return_to_application') }}
                        </a>
                    @else
                        <a href="{{ route('site.borrower.loan-products') }}" class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-full text-sm shadow-sm">
                            {{ __('borrower.face_verification_page.apply_cta') }}
                        </a>
                        <a href="{{ route('site.borrower.profile', ['section' => 'personal']) }}" class="inline-flex bg-white ring-1 ring-brand/15 hover:bg-brand-muted/30 text-brand font-semibold px-6 py-3 rounded-full text-sm">
                            {{ __('borrower.face_verification_page.back_to_documents') }}
                        </a>
                    @endif
                </div>
            </div>

        @elseif ($faceStatus === 'pending')
            <x-site.face-verification-status :customer="$customer" :photos="$photos" :angles="$angles" />
            <div class="mt-6 rounded-3xl bg-gradient-to-b from-sky-50/80 to-white ring-1 ring-sky-200/80 px-6 py-8 text-center">
                <p class="text-sm text-sky-950 font-semibold">{{ __('borrower.nida.face_submitted_title') }}</p>
                <p class="text-xs text-sky-800 mt-2 max-w-md mx-auto">{{ __('borrower.nida.face_submitted_body') }}</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    @if (! empty($returnUrl))
                        <a href="{{ $returnUrl }}"
                           class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-full text-sm shadow-sm">
                            {{ __('borrower.apply.kyc_return_to_application') }}
                        </a>
                    @elseif ($wizardMode ?? false)
                        <a href="{{ app(\App\Services\ProfileWizardService::class)->navigation($customer, 'face')['next']['url'] ?? route('site.borrower.profile', ['section' => 'residence', 'wizard' => 1]) }}"
                           class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-full text-sm shadow-sm">
                            {{ __('borrower.profile_wizard.save_continue') }}
                        </a>
                    @else
                        <a href="{{ route('site.borrower.dashboard') }}" class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-full text-sm shadow-sm">
                            {{ __('borrower.profile_wizard.finish') }}
                        </a>
                    @endif
                </div>
            </div>

        @else
            <x-site.face-verification-wizard
                :customer="$customer"
                :angles="$angles"
                :wizard="$wizard"
                :photos="$photos"
                :steps="$steps"
                :upload-urls="$uploadUrls"
                :delete-urls="$deleteUrls ?? []"
                :submit-url="route('site.borrower.face-verification.submit')"
            />
        @endif
    </div>

    @if ($wizardMode ?? false)
        @include('site.borrower.profile._wizard_footer', ['customer' => $customer, 'wizardMode' => true, 'wizardKey' => $wizardKey ?? 'face'])
    @endif

    @stack('scripts')
</x-site.borrower-layout>
