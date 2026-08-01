<x-site.borrower-layout :title="brand_title(__('borrower.apply.success.submitted_title'))" active="loans" content-width="wide">
    <div class="text-center py-6" x-data x-init="
        (() => {
            const colors = ['#0B3D32','#FBBF24','#34D399','#F59E0B','#A7F3D0'];
            const root = document.createElement('div');
            root.className = 'pointer-events-none fixed inset-0 z-[80] overflow-hidden';
            document.body.appendChild(root);
            for (let i = 0; i < 80; i++) {
                const conf = document.createElement('span');
                const size = 6 + Math.random() * 8;
                conf.style.cssText = [
                    'position:absolute',
                    'top:-12px',
                    'left:' + (Math.random() * 100) + '%',
                    'width:' + size + 'px',
                    'height:' + (size * 0.6) + 'px',
                    'background:' + colors[i % colors.length],
                    'border-radius:' + (Math.random() > 0.5 ? '2px' : '50%'),
                    'opacity:0.95',
                    'transform:rotate(' + (Math.random() * 360) + 'deg)',
                    'animation:kf-confetti-fall ' + (2.2 + Math.random() * 2.4) + 's linear ' + (Math.random() * 0.8) + 's forwards',
                ].join(';');
                root.appendChild(conf);
            }
            setTimeout(() => root.remove(), 5200);
        })()
    ">
        <style>
            @keyframes kf-confetti-fall {
                0% { transform: translateY(0) rotate(0deg); opacity: 1; }
                100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
            }
        </style>
        <div class="relative mx-auto mb-5 size-20">
            <div class="absolute inset-0 rounded-full bg-brand-gold/30 animate-ping"></div>
            <div class="relative size-20 rounded-full bg-gradient-to-br from-brand to-brand-light text-white grid place-items-center shadow-lg">
                <svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
            </div>
        </div>
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('borrower.apply.success.celebration_eyebrow') }}</p>
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight animate-[fadeIn_0.5s_ease-out]">{{ __('borrower.apply.success.submitted_title') }}</h1>
        <p class="mt-2 text-gray-600">{{ __('borrower.apply.success.reference_label') }} <span class="font-mono font-bold text-gray-900">{{ $application->application_number }}</span></p>

        @if (session('status'))
            <p class="mt-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
        @endif

        @if ($guarantorInvitation ?? null)
            <p class="mt-3 text-sm text-brand">{{ __('borrower.apply.success.submitted_guarantor_pending_message') }}</p>
        @endif

        <div class="mt-6 text-left rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm" x-data="{ copied: false }">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.success.tracking_share_title') }}</p>
                <p class="text-sm text-white/90 mt-1">{{ __('borrower.apply.success.tracking_share_hint') }}</p>
            </div>
            <div class="px-5 py-4 flex flex-wrap gap-2">
                <a href="{{ $trackingShareUrl }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.apply.success.tracking_share_whatsapp') }}
                </a>
                <button type="button"
                        @click="navigator.clipboard.writeText(@js($trackingUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                        class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                    <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.apply.success.tracking_share_copy'))"></span>
                </button>
            </div>
        </div>

        @if ($guarantorInvitation ?? null)
            <div class="mt-6 text-left rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm" x-data="{ copied: false }">
                <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white space-y-2">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.guarantor_fields.share_via') }}</p>
                    <p class="text-sm text-white/90">{{ __('borrower.apply.guarantor_fields.share_ready') }}</p>
                    @if ($guarantorInvitationUrl ?? null)
                        <p class="text-xs font-mono text-brand bg-brand-gold/90 rounded-xl px-3 py-2.5 break-all">{{ $guarantorInvitationUrl }}</p>
                    @endif
                </div>
                <div class="px-5 py-4 space-y-4">
                    @if ($combinedShareUrl ?? null)
                        <div class="pb-4 border-b border-gray-100">
                            <p class="text-xs text-gray-600 mb-2">{{ __('borrower.apply.success.combined_share_hint') }}</p>
                            <a href="{{ $combinedShareUrl }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.apply.success.combined_share_whatsapp') }}
                            </a>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        @if ($guarantorShareUrl ?? null)
                            <a href="{{ $guarantorShareUrl }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.apply.guarantor_fields.share_whatsapp') }}
                            </a>
                        @endif
                        @if ($guarantorSmsUrl ?? null)
                            <a href="{{ $guarantorSmsUrl }}"
                               class="inline-flex items-center gap-2 bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.apply.guarantor_fields.share_sms') }}
                            </a>
                        @endif
                        @if ($guarantorEmailUrl ?? null)
                            <a href="{{ $guarantorEmailUrl }}"
                               class="inline-flex items-center gap-2 bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.apply.guarantor_fields.share_email') }}
                            </a>
                        @endif
                        @if ($guarantorInvitationUrl ?? null)
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js($guarantorInvitationUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                                <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.apply.guarantor_fields.share_copy'))"></span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-8 bg-white rounded-2xl border border-gray-200 p-6 text-left">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-4">{{ __('borrower.loan_profile.application_progress') }}</p>
            <ol class="space-y-3">
                @foreach ($underwritingStages as $stage)
                    @php
                        $dotClass = match ($stage['status']) {
                            'done'   => 'bg-emerald-500',
                            'active' => 'bg-amber-500 ring-4 ring-amber-100',
                            default  => 'bg-gray-200',
                        };
                        $textClass = match ($stage['status']) {
                            'done'   => 'text-emerald-800',
                            'active' => 'text-amber-900 font-semibold',
                            default  => 'text-gray-500',
                        };
                    @endphp
                    <li class="flex items-center gap-3">
                        <span class="size-3 rounded-full shrink-0 {{ $dotClass }}"></span>
                        <span class="text-sm {{ $textClass }}">{{ $stage['label'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-6 text-left">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="text-gray-500">{{ __('borrower.apply.review_step.product') }}</div>
                <div class="font-medium">{{ $application->product->name }}</div>
                <div class="text-gray-500">{{ __('borrower.apply.review_step.loan_amount') }}</div>
                <div class="font-medium">{{ format_money($application->requested_amount, true, 0) }}</div>
                <div class="text-gray-500">{{ __('borrower.apply.review_step.duration') }}</div>
                <div class="font-medium">{{ __('borrower.applications_list.tenure_months', ['count' => $application->requested_tenure_months]) }}</div>
            </div>
        </div>

        <div class="mt-8 flex gap-3 justify-center flex-wrap">
            <a href="{{ route('site.borrower.application', $application->id) }}"
               class="bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                {{ __('borrower.apply.success.view_application') }}
            </a>
            <a href="{{ route('site.borrower.dashboard') }}"
               class="bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-5 py-2.5 rounded-xl text-sm">
                {{ __('borrower.apply.success.dashboard') }}
            </a>
        </div>
    </div>
</x-site.borrower-layout>
