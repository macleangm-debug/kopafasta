<x-site.layout :title="brand_title(__('site.partner_apply.track_title'))">
    <section class="bg-brand text-white">
        <div class="max-w-2xl mx-auto px-4 py-10">
            <a href="{{ route('site.partners') }}" class="text-sm text-white/70 hover:text-white inline-flex items-center gap-1 mb-4">
                ← {{ __('site.partners.title') }}
            </a>
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-2">{{ brand_name() }}</p>
            <h1 class="text-3xl font-bold tracking-tight">{{ __('site.partner_apply.track_title') }}</h1>
            <p class="text-sm text-white/80 mt-2">{{ __('site.partner_apply.track_subtitle') }}</p>
        </div>
    </section>

    <div class="max-w-2xl mx-auto py-10 px-4 -mt-6 space-y-5">
        @if (session('status'))
            <div class="rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white px-5 py-5 shadow-lg ring-1 ring-brand/20">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.feedback.tones.success') }}</p>
                <p class="mt-2 text-sm leading-relaxed">{{ session('status') }}</p>
            </div>
        @endif

        <form method="GET" action="{{ route('site.partners.apply.tracking') }}" class="glass-card p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('site.partner_apply.track_phone_label') }}</label>
                <input type="text" name="phone" value="{{ $phone }}" required
                       class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                       placeholder="{{ __('site.partner_apply.track_phone_placeholder') }}">
            </div>
            <button type="submit" class="w-full sm:w-auto bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">
                {{ __('site.partner_apply.track_submit') }}
            </button>
        </form>

        @if ($phone !== '')
            @forelse ($applications as $application)
                <div class="glass-card p-5 space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.partner_apply.track_application') }}</p>
                            <p class="text-lg font-bold text-gray-900 mt-1">{{ $application->business_name ?: $application->full_name }}</p>
                            <p class="text-sm text-gray-600">{{ \App\Services\PartnerEnrollmentService::ENROLLABLE_CATEGORIES[$application->partner_category] ?? ucfirst(str_replace('_', ' ', (string) $application->partner_category)) }}</p>
                        </div>
                        @php
                            $status = (string) $application->status;
                            $tone = match ($status) {
                                'approved' => 'bg-brand-muted text-brand ring-brand/20',
                                'rejected' => 'bg-red-50 text-red-700 ring-red-200',
                                default => 'bg-brand-muted/60 text-brand ring-brand/15',
                            };
                        @endphp
                        <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 ring-1 {{ $tone }}">
                            {{ __('site.partner_apply.track_statuses.'.$status) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">{{ __('site.partner_apply.track_phone_used', ['phone' => $application->phone]) }}</p>
                    <p class="text-xs text-gray-500">{{ __('site.partner_apply.track_submitted', ['date' => optional($application->created_at)->format('d M Y H:i')]) }}</p>

                    @if ($status === 'approved')
                        <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/15 px-4 py-3 text-sm text-brand">
                            <p class="font-semibold">{{ __('site.partner_apply.track_approved_title') }}</p>
                            <p class="mt-1 text-brand/80">{{ __('site.partner_apply.track_approved_body') }}</p>
                            <a href="{{ route('site.login', ['portal' => 'partner']) }}"
                               class="inline-flex mt-3 bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-xl text-sm">
                                {{ __('site.partner_apply.track_login_cta') }}
                            </a>
                        </div>
                    @elseif ($status === 'rejected')
                        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
                            <p class="font-semibold">{{ __('site.partner_apply.track_rejected_title') }}</p>
                            <p class="mt-1">{{ $application->admin_notes ?: __('site.partner_apply.track_rejected_body') }}</p>
                        </div>
                    @else
                        <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3 text-sm text-brand">
                            {{ __('site.partner_apply.track_pending_body') }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="glass-card p-6 text-sm text-gray-600">
                    {{ __('site.partner_apply.track_empty') }}
                </div>
            @endforelse
        @endif
    </div>
</x-site.layout>
