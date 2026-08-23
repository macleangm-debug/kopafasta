<x-site.layout :title="brand_title(__('site.partner_apply.track_title'))">
    @php
        $showSubmittedModal = session()->pull('partner_submitted');
        $enrolledPartner = $enrolledPartner ?? null;
        $resultPayload = null;
        if ($phone !== '' && $applications->isNotEmpty()) {
            $app = $applications->first();
            $partner = $app->partner ?: $enrolledPartner;
            $resultPayload = [
                'status' => (string) $app->status,
                'name' => $app->business_name ?: $app->full_name,
                'category' => \App\Services\PartnerEnrollmentService::ENROLLABLE_CATEGORIES[$app->partner_category] ?? ucfirst(str_replace('_', ' ', (string) $app->partner_category)),
                'phone' => $app->phone,
                'submitted' => optional($app->created_at)->format('d M Y H:i'),
                'notes' => $app->admin_notes,
                'partner_code' => $partner?->vendor_number ?: $partner?->partner_number,
                'activated' => (bool) ($partner?->activated_at && $partner?->user_id),
                'activate_url' => $partner
                    ? app(\App\Services\PartnerActivationService::class)->publicActivateUrl($partner)
                    : route('site.partner.start'),
            ];
        } elseif ($phone !== '' && $enrolledPartner) {
            $resultPayload = [
                'status' => ($enrolledPartner->activated_at && $enrolledPartner->user_id) ? 'approved' : 'approved',
                'name' => $enrolledPartner->name,
                'category' => ucfirst(str_replace('_', ' ', (string) $enrolledPartner->category)),
                'phone' => $enrolledPartner->phone,
                'submitted' => optional($enrolledPartner->created_at)->format('d M Y H:i'),
                'notes' => null,
                'partner_code' => $enrolledPartner->vendor_number ?: $enrolledPartner->partner_number,
                'activated' => (bool) ($enrolledPartner->activated_at && $enrolledPartner->user_id),
                'activate_url' => app(\App\Services\PartnerActivationService::class)->publicActivateUrl($enrolledPartner),
                'enrolled_direct' => true,
            ];
        } elseif ($phone !== '') {
            $resultPayload = ['empty' => true];
        }
    @endphp

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

    <div class="max-w-2xl mx-auto py-10 px-4 -mt-6 space-y-5"
         x-data="{
            resultOpen: {{ $resultPayload ? 'true' : 'false' }},
            submittedOpen: {{ $showSubmittedModal ? 'true' : 'false' }},
         }">
        <form method="GET" action="{{ route('site.partners.apply.tracking') }}" class="glass-card p-6 space-y-4">
            <x-site.phone-input
                name="phone"
                :label="__('site.partner_apply.track_phone_label')"
                :value="$phone"
                variant="rounded"
                :required="true"
                :help="__('site.partner_apply.track_phone_help')"
            />
            <button type="submit" class="w-full sm:w-auto bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">
                {{ __('site.partner_apply.track_submit') }}
            </button>
        </form>

        {{-- Submitted modal --}}
        <div x-show="submittedOpen" x-cloak class="fixed inset-0 z-[10050] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm" @click="submittedOpen = false"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15"
                 @keydown.escape.window="submittedOpen = false">
                <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
                    <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.feedback.tones.success') }}</p>
                    <h2 class="mt-1 text-xl font-bold">{{ __('site.partner_apply.success_modal_title') }}</h2>
                </div>
                <div class="px-6 py-5 space-y-4 text-sm text-gray-700">
                    <p>{{ __('site.partner_apply.success_modal_body') }}</p>
                    <a href="{{ route('site.partners.apply.tracking') }}"
                       class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                        {{ __('site.partner_apply.track_cta') }}
                    </a>
                    <button type="button" @click="submittedOpen = false"
                            class="w-full text-sm font-semibold text-gray-600 hover:text-gray-900 py-2">
                        {{ __('borrower.feedback.ok') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Status result modal --}}
        @if ($resultPayload)
            <div x-show="resultOpen" x-cloak class="fixed inset-0 z-[10050] flex items-center justify-center p-4" role="dialog" aria-modal="true"
                 @keydown.escape.window="resultOpen = false">
                <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm" @click="resultOpen = false"></div>
                <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15">
                    @if (! empty($resultPayload['empty']))
                        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
                            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.partner_apply.track_application') }}</p>
                            <h2 class="mt-1 text-xl font-bold">{{ __('site.partner_apply.track_empty_title') }}</h2>
                        </div>
                        <div class="px-6 py-5 space-y-4 text-sm text-gray-700">
                            <p>{{ __('site.partner_apply.track_empty') }}</p>
                            <button type="button" @click="resultOpen = false"
                                    class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.feedback.ok') }}
                            </button>
                        </div>
                    @else
                        @php $status = $resultPayload['status']; @endphp
                        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
                            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.partner_apply.track_application') }}</p>
                            <h2 class="mt-1 text-xl font-bold">{{ $resultPayload['name'] }}</h2>
                            <p class="text-sm text-white/80 mt-1">{{ $resultPayload['category'] }}</p>
                            <span class="inline-flex mt-3 text-xs font-semibold rounded-full px-3 py-1 bg-white/15 ring-1 ring-white/25">
                                {{ __('site.partner_apply.track_statuses.'.$status) }}
                            </span>
                        </div>
                        <div class="px-6 py-5 space-y-4 text-sm text-gray-700">
                            <p class="text-xs text-gray-500">{{ __('site.partner_apply.track_submitted', ['date' => $resultPayload['submitted']]) }}</p>

                            @if ($status === 'approved')
                                <div class="rounded-2xl bg-brand-muted/50 ring-1 ring-brand/15 p-4 space-y-3">
                                    <p class="font-semibold text-brand">{{ __('site.partner_apply.track_approved_title') }}</p>
                                    @if ($resultPayload['partner_code'])
                                        <div>
                                            <p class="text-[10px] uppercase tracking-widest text-brand/70 font-semibold">{{ __('site.partner_apply.track_partner_code') }}</p>
                                            <p class="mt-1 text-2xl font-extrabold tracking-widest font-mono text-brand">{{ $resultPayload['partner_code'] }}</p>
                                            <p class="mt-2 text-xs text-brand/80">{{ __('site.partner_apply.track_partner_code_hint') }}</p>
                                        </div>
                                    @endif
                                    @if ($resultPayload['activated'])
                                        <a href="{{ route('site.login.partner') }}"
                                           class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                            {{ __('site.partner_apply.track_login_cta') }}
                                        </a>
                                    @else
                                        <a href="{{ $resultPayload['activate_url'] ?? route('site.partner.start') }}"
                                           class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                            {{ __('site.partner_apply.track_activate_cta') }}
                                        </a>
                                    @endif
                                </div>
                            @elseif ($status === 'rejected')
                                <div class="rounded-2xl bg-red-50 ring-1 ring-red-200 p-4 text-red-800">
                                    <p class="font-semibold">{{ __('site.partner_apply.track_rejected_title') }}</p>
                                    <p class="mt-1">{{ $resultPayload['notes'] ?: __('site.partner_apply.track_rejected_body') }}</p>
                                </div>
                            @elseif ($status === 'needs_info')
                                <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-4 text-amber-900">
                                    <p class="font-semibold">{{ __('site.partner_apply.track_needs_info_title') }}</p>
                                    <p class="mt-1">{{ $resultPayload['notes'] ?: __('site.partner_apply.track_needs_info_body') }}</p>
                                </div>
                            @else
                                <div class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 text-brand">
                                    {{ __('site.partner_apply.track_pending_body') }}
                                </div>
                            @endif

                            <button type="button" @click="resultOpen = false"
                                    class="w-full text-sm font-semibold text-gray-600 hover:text-gray-900 py-2">
                                {{ __('borrower.feedback.ok') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-site.layout>
