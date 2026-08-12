{{-- Submit step — signature-first; group shows member signature readiness --}}
<div x-show="stepKey === 'submit'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.submit')"
        :title="__('borrower.apply.submit_step.title')"
        :subtitle="__('borrower.apply.submit_step.subtitle')"
    />

    <div x-show="!canApply" x-cloak class="mb-6 rounded-2xl overflow-hidden ring-1 ring-brand/20 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-5 text-white">
            <p class="text-[10px] uppercase tracking-[0.18em] font-semibold text-brand-gold">{{ __('borrower.apply.complete_profile_to_submit') }}</p>
            <p class="mt-2 text-lg font-bold tracking-tight">{{ __('borrower.apply.kyc_incomplete_title') }}</p>
            <p class="mt-1 text-sm text-white/85">{{ __('borrower.apply.kyc_incomplete_submit_hint') }}</p>
            <button type="button"
                    @click="showProfileGateModal = true"
                    class="mt-4 inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                {{ __('borrower.loan_profile.complete_profile') }}
            </button>
        </div>
    </div>

    <div x-show="supplementMode" x-cloak class="glass-card rounded-2xl ring-1 ring-sky-200 bg-gradient-to-br from-sky-50 to-white px-5 py-4 text-sm text-sky-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.submit_step.supplement_title') }}</p>
        <p class="mt-1 text-sky-800">{{ __('borrower.apply.submit_step.supplement_hint') }}</p>
    </div>

    {{-- Group members: readiness roster only (5 per page). Signatures live in one card below. --}}
    <section x-show="isGroupProduct(current) && (group.members || []).length" x-cloak
             class="mb-6">
        <div class="rounded-3xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm">
            <div class="px-5 sm:px-6 py-4 bg-gradient-to-r from-brand-muted/50 to-white border-b border-brand/10 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.submit_step.group_signatures_title') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.apply.submit_step.group_signatures_hint') }}</p>
                </div>
                <p class="text-xs font-semibold text-gray-500 tabular-nums"
                   x-show="groupRosterPages() > 1"
                   x-cloak
                   x-text="(groupRosterPage + 1) + ' / ' + groupRosterPages()"></p>
            </div>
            <ul class="divide-y divide-gray-100">
                <template x-for="(member, localIndex) in groupRosterPageMembers()" :key="'sig-m-' + groupRosterAbsoluteIndex(localIndex) + '-' + (member.customer_id || member.invitation_id || localIndex)">
                    <li class="px-5 sm:px-6 py-3.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-brand-muted/20"
                        @click="groupSigSlide = groupRosterAbsoluteIndex(localIndex)">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate"
                               x-text="member.name || member.label || member.phone || ('#' + (groupRosterAbsoluteIndex(localIndex) + 1))"></p>
                            <p class="text-xs mt-0.5" :class="memberStatusClass(member)" x-text="memberStatusLabel(member)"></p>
                        </div>
                        <span class="shrink-0 size-8 rounded-full grid place-items-center ring-1"
                              :class="(member.status_key || '') === 'kyc_complete' || member.signed
                                  ? 'bg-brand text-brand-gold ring-brand-gold/40'
                                  : 'bg-amber-50 text-amber-700 ring-amber-200'">
                            <template x-if="(member.status_key || '') === 'kyc_complete' || member.signed">
                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                                </svg>
                            </template>
                            <template x-if="(member.status_key || '') !== 'kyc_complete' && ! member.signed">
                                <span class="text-[10px] font-bold" x-text="groupRosterAbsoluteIndex(localIndex) + 1"></span>
                            </template>
                        </span>
                    </li>
                </template>
            </ul>
            <div x-show="groupRosterPages() > 1" x-cloak class="px-5 sm:px-6 py-3 border-t border-gray-100 flex items-center justify-between gap-3">
                <button type="button" @click="groupRosterPrevPage()" class="text-xs font-semibold text-brand hover:underline disabled:opacity-40"
                        :disabled="groupRosterPage === 0">← {{ __('borrower.apply.group.signature_carousel_prev') }}</button>
                <div class="flex gap-1.5">
                    <template x-for="p in groupRosterPages()" :key="'roster-dot-' + p">
                        <button type="button" @click="groupRosterPage = p - 1" class="size-2 rounded-full"
                                :class="groupRosterPage === (p - 1) ? 'bg-brand' : 'bg-gray-300'"></button>
                    </template>
                </div>
                <button type="button" @click="groupRosterNextPage()" class="text-xs font-semibold text-brand hover:underline disabled:opacity-40"
                        :disabled="groupRosterPage >= groupRosterPages() - 1">{{ __('borrower.apply.group.signature_carousel_next') }} →</button>
            </div>
        </div>
    </section>

    {{-- Guarantor: live status + progress on submit --}}
    <section x-show="hasStep('guarantor') && form.guarantor_mode && form.guarantor_mode !== 'none'" x-cloak
             class="mb-6 rounded-2xl ring-1 px-4 py-3.5 space-y-3"
             :class="guarantorStatusCode() === 'ready' ? 'ring-emerald-200/80 bg-emerald-50/80' : 'ring-amber-200/80 bg-amber-50/80'">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0 flex items-center gap-3">
                <span class="shrink-0 size-9 rounded-full grid place-items-center bg-white ring-1 text-lg"
                      :class="guarantorStatusCode() === 'ready' ? 'ring-emerald-200 text-emerald-800' : 'ring-amber-200 text-amber-800'"
                      aria-hidden="true">🤝</span>
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-widest font-semibold"
                       :class="guarantorStatusCode() === 'ready' ? 'text-emerald-800' : 'text-amber-800'"
                       x-text="guarantorHoldTitle()"></p>
                    <p class="text-sm font-semibold truncate mt-0.5"
                       :class="guarantorStatusCode() === 'ready' ? 'text-emerald-950' : 'text-amber-950'"
                       x-text="(review.guarantorName || guarantorSummaryText()) + ' · ' + guarantorStatusLabel()"></p>
                </div>
            </div>
            <p class="shrink-0 text-[11px] font-semibold hidden sm:block max-w-[14rem] text-right"
               :class="guarantorStatusCode() === 'ready' ? 'text-emerald-800/90' : 'text-amber-800/90'"
               x-text="guarantorHoldHint()"></p>
        </div>
        <ol x-show="guarantorProgressSteps().length" x-cloak class="grid sm:grid-cols-4 gap-2">
            <template x-for="step in guarantorProgressSteps()" :key="step.key">
                <li class="rounded-xl bg-white/80 ring-1 px-3 py-2"
                    :class="step.complete ? 'ring-emerald-200' : (step.current ? 'ring-amber-300' : 'ring-gray-200')">
                    <p class="text-[10px] uppercase tracking-widest font-semibold"
                       :class="step.complete ? 'text-emerald-700' : (step.current ? 'text-amber-800' : 'text-gray-400')"
                       x-text="step.complete ? '✓' : (step.current ? '·' : '○')"></p>
                    <p class="text-xs font-semibold mt-0.5"
                       :class="step.current ? 'text-gray-900' : 'text-gray-600'"
                       x-text="step.label"></p>
                </li>
            </template>
        </ol>
    </section>

    {{-- One signature card: for groups, name/signature carousel; leader confirms here --}}
    <section class="relative overflow-hidden rounded-3xl ring-1 ring-brand/15 bg-white shadow-lg shadow-brand/10 mb-2"
             x-show="!supplementMode"
             x-cloak>
        <div class="absolute inset-x-0 top-0 h-28 bg-gradient-to-br from-brand via-brand to-brand-light" aria-hidden="true"></div>
        <div class="absolute inset-x-0 top-0 h-28 opacity-20" style="background-image: radial-gradient(circle at 15% 20%, #fff 0, transparent 40%), radial-gradient(circle at 85% 0%, #f5c842 0, transparent 35%);" aria-hidden="true"></div>

        <div class="relative px-5 sm:px-7 pt-6 pb-2">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">{{ __('borrower.apply.signature_draw_label') }}</p>
                    <p class="mt-2 text-xl sm:text-2xl font-bold text-white tracking-tight truncate"
                       x-text="groupSigSlideName()"></p>
                    <p x-show="identityVerified || (isGroupProduct(current) && groupSigCurrentMember()?.signed)" x-cloak
                       class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold bg-white/15 text-white px-2.5 py-1 rounded-lg">
                        <span aria-hidden="true">✓</span>
                        <span x-text="isGroupProduct(current) && ! groupSigIsLeaderSlide()
                            ? @js(__('borrower.apply.group.signature_carousel_signed'))
                            : @js(__('borrower.apply.signature_verified'))"></span>
                    </p>
                </div>
                <p x-show="isGroupProduct(current) && (group.members || []).length > 1" x-cloak
                   class="shrink-0 text-xs font-semibold text-white/80 tabular-nums"
                   x-text="(groupSigSlide + 1) + ' / ' + (group.members || []).length"></p>
            </div>
        </div>

        <div class="relative mx-4 sm:mx-6 mb-5 -mt-1 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            {{-- Leader / individual: consent + own signature --}}
            <div x-show="! isGroupProduct(current) || groupSigIsLeaderSlide()" x-cloak>
                <div class="px-4 sm:px-5 pt-4 pb-3 border-b border-gray-100">
                    <label class="flex items-start gap-3 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox"
                               name="borrower_consent"
                               value="1"
                               x-model="declarationAccepted"
                               @change="persistDeclaration()"
                               class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="leading-snug">{{ __('borrower.apply.signature_consent', ['brand' => brand_name()]) }}</span>
                    </label>
                </div>

                <div class="p-4 sm:p-5"
                     :class="declarationAccepted ? '' : 'opacity-55 pointer-events-none'">
                    <div x-show="borrowerSignature?.signature_data && !resigningOnSubmit" x-cloak>
                        <div class="rounded-2xl bg-[linear-gradient(180deg,#f8faf9_0%,#ffffff_55%)] ring-1 ring-brand/10 px-3 py-4 min-h-[9rem] grid place-items-center">
                            <img :src="borrowerSignature.signature_data"
                                 alt=""
                                 class="max-h-28 w-auto max-w-full object-contain">
                        </div>
                    </div>

                    <template x-if="!borrowerSignature?.signature_data || resigningOnSubmit">
                        <div>
                            <x-site.signature-pad
                                :default-name="$verifiedLegalName"
                                :readonly-name="true"
                                :verified="$identityVerified"
                                :include-in-form="false"
                                compact
                                hide-clear />
                        </div>
                    </template>
                </div>
            </div>

            {{-- Other group members: read-only signed / waiting --}}
            <div x-show="isGroupProduct(current) && ! groupSigIsLeaderSlide()" x-cloak class="p-4 sm:p-5">
                <div class="rounded-2xl bg-[linear-gradient(180deg,#f8faf9_0%,#ffffff_55%)] ring-1 ring-brand/10 px-3 py-5 min-h-[9rem] grid place-items-center">
                    <template x-if="groupSigCurrentMember()?.signature_data">
                        <img :src="groupSigCurrentMember().signature_data" alt="" class="max-h-28 w-auto max-w-full object-contain">
                    </template>
                    <template x-if="! groupSigCurrentMember()?.signature_data">
                        <div class="text-center space-y-2 px-4">
                            <p class="text-sm font-semibold text-amber-950">{{ __('borrower.apply.group.signature_carousel_waiting') }}</p>
                            <p class="text-xs text-amber-900/80">{{ __('borrower.apply.group.signature_carousel_waiting_hint') }}</p>
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="isGroupProduct(current) && (group.members || []).length > 1" x-cloak
                 class="px-4 sm:px-5 pb-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-3">
                <button type="button" @click="groupSigPrev()" class="text-xs font-semibold text-brand hover:underline">← {{ __('borrower.apply.group.signature_carousel_prev') }}</button>
                <div class="flex gap-1.5 flex-wrap justify-center max-w-[14rem]">
                    <template x-for="(member, index) in (group.members || [])" :key="'sig-dot-' + index">
                        <button type="button" @click="groupSigSlide = index" class="size-2 rounded-full"
                                :class="groupSigSlide === index ? 'bg-brand' : (member.signed || member.role === 'leader' ? 'bg-emerald-400' : 'bg-gray-300')"></button>
                    </template>
                </div>
                <button type="button" @click="groupSigNext()" class="text-xs font-semibold text-brand hover:underline">{{ __('borrower.apply.group.signature_carousel_next') }} →</button>
            </div>
        </div>
    </section>

    <input type="hidden" name="signature_data" data-submit-signature>
    <input type="hidden" name="signer_name" data-submit-signer>
    <input type="hidden" name="consent" value="1">
</div>

