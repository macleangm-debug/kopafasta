@php
    $invites = $dossier['guarantor_invitations'] ?? collect();

    $statusMeta = static function (?string $status): array {
        $status = strtolower((string) $status);

        return match ($status) {
            'accepted', 'approved', 'signed', 'completed' => [
                'label' => 'Accepted',
                'help' => 'This person agreed to guarantee.',
                'tone' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            ],
            'declined', 'rejected' => [
                'label' => 'Declined',
                'help' => 'This person declined the invitation.',
                'tone' => 'bg-red-100 text-red-800 ring-red-200',
            ],
            'expired' => [
                'label' => 'Expired',
                'help' => 'The invitation timed out before a response.',
                'tone' => 'bg-gray-100 text-gray-700 ring-gray-200',
            ],
            'pending', 'sent', 'invited' => [
                'label' => 'Waiting',
                'help' => 'Invitation sent — waiting for the guarantor to respond.',
                'tone' => 'bg-amber-100 text-amber-900 ring-amber-200',
            ],
            default => [
                'label' => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Unknown',
                'help' => 'Invitation status on this application.',
                'tone' => 'bg-sky-100 text-sky-800 ring-sky-200',
            ],
        };
    };
@endphp

<div class="space-y-5">
    <div class="rounded-2xl bg-gradient-to-r from-brand-muted/50 to-white px-5 py-4 ring-1 ring-brand/10">
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Guarantee support</p>
        <h4 class="text-base font-bold text-gray-900 mt-0.5">Who was asked to guarantee</h4>
        <p class="text-xs text-gray-600 mt-1 max-w-2xl leading-relaxed">
            Each card is one invitation this member sent (or that was sent for them) asking someone else to stand as guarantor on a loan application.
            Open the application to see the full underwriting context.
        </p>
    </div>

    @if ($invites->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-5 py-12 text-center">
            <p class="text-sm font-semibold text-gray-700">No guarantor invitations yet</p>
            <p class="text-xs text-gray-500 mt-1">When this member needs a guarantor, invitations will appear here.</p>
        </div>
    @else
        <ul class="space-y-3">
            @foreach ($invites as $invite)
                @php
                    $meta = $statusMeta($invite->status);
                    $guarantorName = $invite->invitee_name
                        ?: $invite->guarantorCustomer?->full_name
                        ?: ($invite->customerGuarantor ? $invite->customerGuarantor->displayName() : null)
                        ?: $invite->guarantor_signer_name
                        ?: 'Guarantor';
                    $contact = $invite->contact ?: $invite->guarantorCustomer?->phone;
                    $app = $invite->application;
                    $appNumber = $app?->application_number;
                    $product = $app?->product?->name;
                    $when = $invite->created_at?->timezone(config('app.timezone'));
                    $responded = $invite->responded_at?->timezone(config('app.timezone'))
                        ?? $invite->guarantor_signed_at?->timezone(config('app.timezone'));
                    $expired = $invite->isExpired() && in_array(strtolower((string) $invite->status), ['pending', 'sent', 'invited'], true);
                    if ($expired) {
                        $meta = $statusMeta('expired');
                    }
                @endphp
                <li class="rounded-2xl bg-white shadow-sm ring-1 ring-brand/10 overflow-hidden">
                    <div class="px-4 sm:px-5 py-4 flex flex-col lg:flex-row lg:items-start gap-4">
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Invited guarantor</p>
                                    <p class="text-base font-bold text-gray-900 mt-0.5">{{ $guarantorName }}</p>
                                    @if ($contact)
                                        <p class="text-sm text-gray-600 mt-0.5">{{ $contact }}</p>
                                    @endif
                                </div>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 {{ $meta['tone'] }}">
                                    {{ $meta['label'] }}
                                </span>
                            </div>

                            <p class="text-xs text-gray-500 leading-relaxed">{{ $meta['help'] }}</p>

                            <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                                    <dt class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">For application</dt>
                                    <dd class="mt-1 font-semibold text-gray-900">
                                        @if ($app)
                                            <a href="{{ route('admin.loan-applications.show', $app) }}" class="text-brand hover:underline">
                                                {{ $appNumber ?? 'Application #'.$app->id }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </dd>
                                    @if ($product)
                                        <dd class="text-xs text-gray-500 mt-0.5">{{ $product }}</dd>
                                    @endif
                                </div>
                                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                                    <dt class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Channel</dt>
                                    <dd class="mt-1 font-semibold text-gray-900 capitalize">
                                        {{ $invite->channel ? str_replace('_', ' ', $invite->channel) : '—' }}
                                        @if ($invite->type)
                                            <span class="text-gray-400 font-normal">·</span>
                                            <span class="font-normal text-gray-600 capitalize">{{ str_replace('_', ' ', $invite->type) }}</span>
                                        @endif
                                    </dd>
                                </div>
                                @if ($invite->requested_amount)
                                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                                        <dt class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Requested cover</dt>
                                        <dd class="mt-1 font-semibold tabular-nums text-gray-900">
                                            {{ format_money((float) $invite->requested_amount) }}
                                            @if ($invite->requested_tenure_months)
                                                <span class="text-xs font-normal text-gray-500">· {{ $invite->requested_tenure_months }} mo</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                                    <dt class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">Timeline</dt>
                                    <dd class="mt-1 text-gray-900">
                                        <span class="font-semibold">Sent</span>
                                        {{ $when?->format('d M Y · H:i') ?? '—' }}
                                    </dd>
                                    @if ($responded)
                                        <dd class="text-xs text-gray-500 mt-0.5">
                                            Responded {{ $responded->format('d M Y · H:i') }}
                                        </dd>
                                    @elseif ($invite->expires_at)
                                        <dd class="text-xs text-gray-500 mt-0.5">
                                            Expires {{ $invite->expires_at->timezone(config('app.timezone'))->format('d M Y · H:i') }}
                                        </dd>
                                    @endif
                                </div>
                            </dl>

                            @if ($invite->response_notes)
                                <p class="text-xs text-gray-600 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-3 py-2">
                                    {{ $invite->response_notes }}
                                </p>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
