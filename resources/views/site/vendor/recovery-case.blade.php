<x-site.vendor-layout title="Recovery case #{{ $assignment->id }}" active="recovery">
    @php
        $customer = $customer ?? $loan?->customer;
        $borrowerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        $statusBadge = match ($assignment->status) {
            'assigned'    => 'bg-amber-100 text-amber-700',
            'in_progress' => 'bg-indigo-100 text-brand',
            'completed'   => 'bg-emerald-100 text-emerald-700',
            'escalated'   => 'bg-red-100 text-red-700',
            default       => 'bg-gray-100 text-gray-600',
        };
        $slaBreached = $assignment->slaBreached();
        $isOpen = $assignment->isOpen();
    @endphp

    <div class="mb-5">
        <a href="{{ route('site.partner.recovery-cases') }}" data-kf-motion="pop" class="text-sm text-brand hover:underline">← Back to recovery cases</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-6">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Case #{{ $assignment->arrear_case_id }}</p>
            <h1 class="text-2xl font-extrabold" style="view-transition-name: kf-rec-{{ $assignment->id }}">{{ $borrowerName ?: 'Borrower' }}</h1>
            <p class="text-sm text-gray-600 mt-1">
                {{ display_label($assignment->partner_type, 'recovery_partner_type') }}
                @if ($loan?->loan_number) · {{ $loan->loan_number }} @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($isOpen)
                <form method="POST" action="{{ route('site.partner.recovery-case.remind', $assignment) }}"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('site.partner_portal.confirm.reminder_title')),
                          message: @js(__('site.partner_portal.confirm.reminder_message')),
                          confirmLabel: @js(__('site.partner_portal.confirm.reminder_button')),
                          tone: 'info',
                      })">
                    @csrf
                    <button type="submit" class="rounded-lg border border-brand/30 bg-white text-brand text-xs font-semibold px-3 py-2 hover:bg-brand/5">
                        Send in-app reminder
                    </button>
                </form>
            @endif
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">
                {{ display_label($assignment->status, 'record_status') }}
            </span>
        </div>
    </div>

    @if ($isOpen)
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">{{ __('site.partner_apply.conduct_title') }}</p>
            <p class="mt-1 text-xs leading-relaxed">{{ __('site.partner_apply.conduct_body') }}</p>
            <p class="mt-2 text-xs text-amber-800">Reminders stay in-app only. Do not message unrelated contacts or shame the borrower publicly.</p>
        </div>
    @endif

    @if ($slaBreached && $isOpen)
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">SLA breached</p>
            <p class="mt-1">This case was due {{ $assignment->sla_due_at?->format('d M Y') }}. Please update status or contact collections.</p>
        </div>
    @elseif ($isOpen && $sla_days_remaining !== null && $sla_days_remaining <= 2)
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">Short time left on this SLA</p>
            <p class="mt-1">
                {{ $sla_days_remaining }} day{{ $sla_days_remaining === 1 ? '' : 's' }} left.
                Tell the borrower they must pay soon
                @if (! empty($next_partner_label))
                    or the loan moves to <strong>{{ $next_partner_label }}</strong>
                @endif
                .
            </p>
        </div>
    @endif

    @if (! empty($auction_hold))
        <div class="mb-4">
            <x-site.auction-hold-banner :status="$auction_hold" />
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            @if ($loan)
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Credit file</p>
                    <h2 class="text-lg font-bold text-gray-900 mt-0.5">
                        {{ $loan->status === 'defaulted' ? 'Defaulted facility' : 'Loan in arrears' }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">Same servicing file as credit management, simplified for collections. No write-off or screening actions.</p>
                </div>

                @include('admin.loan-applications.review._management_summary_cards', [
                    'record' => $record ?? $loan->application,
                    'customer' => $customer,
                    'product' => $product ?? $loan->product,
                    'linkedLoan' => $loan,
                    'servicing' => $servicing,
                    'signedContract' => $signedContract ?? null,
                    'workspaceUrl' => null,
                    'letterDownloadUrl' => fn ($agreement) => route('site.partner.recovery-case.letter', [$assignment, $agreement]),
                    'previewMode' => 'partner',
                ])

                @if (! empty($servicing['balance_breakdown']))
                    <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                        <h2 class="font-bold mb-1">Amount owed</h2>
                        <p class="text-xs text-gray-500 mb-4">Principal, interest, penalty and recovery charges as configured in Settings.</p>
                        <x-loan-balance-breakdown
                            :breakdown="$servicing['balance_breakdown']"
                            :recovery-charges="$servicing['recovery_charges'] ?? null"
                            :expanded="true" />
                    </div>
                @endif

                @include('site.vendor._recovery_collection_contacts')
                @include('site.vendor._recovery_profiles')

                <div id="loan-letters" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 scroll-mt-24">
                    <h2 class="font-bold mb-4">Letters on file</h2>
                    @include('admin.loan-applications.review._file_letters', [
                        'record' => $record ?? $loan->application,
                        'offerLetter' => $offer ?? null,
                        'loanContract' => $contract ?? null,
                        'finalContract' => $finalContract ?? null,
                        'signedContract' => $signedContract ?? null,
                        'rejectionLetter' => null,
                        'allowMutations' => false,
                        'featureSignedContract' => true,
                        'useAdminPreview' => false,
                        'embedDocuments' => false,
                        'letterDownloadUrl' => fn ($agreement) => route('site.partner.recovery-case.letter', [$assignment, $agreement]),
                    ])
                </div>
            @endif

            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-3">Assignment</h2>
                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500 text-xs">Borrower</dt>
                        <dd class="font-medium">{{ $borrowerName ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Borrower phone</dt>
                        <dd class="font-medium">{{ $customer?->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Guarantor</dt>
                        <dd class="font-medium">{{ $guarantor_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Guarantor phone</dt>
                        <dd class="font-medium">{{ $guarantor_phone ?? '—' }}</dd>
                    </div>
                    @if (! empty($product_name))
                        <div>
                            <dt class="text-gray-500 text-xs">Product</dt>
                            <dd class="font-medium">{{ $product_name }}</dd>
                        </div>
                    @endif
                    @if (! empty($borrower_region))
                        <div>
                            <dt class="text-gray-500 text-xs">Region / location</dt>
                            <dd class="font-medium">{{ $borrower_region }}</dd>
                        </div>
                    @endif
                    @if (! empty($borrower_address))
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 text-xs">Address</dt>
                            <dd class="font-medium">{{ $borrower_address }}</dd>
                        </div>
                    @endif
                    @if (! empty($branch_name))
                        <div>
                            <dt class="text-gray-500 text-xs">Branch</dt>
                            <dd class="font-medium">{{ $branch_name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500 text-xs">Outstanding at assign</dt>
                        <dd class="font-semibold text-red-700">{{ format_money($assignment->original_outstanding) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Your commission on this case</dt>
                        <dd class="font-semibold">{{ format_money($assignment->commission_earned) }}</dd>
                        <a href="{{ $wallet_url ?? route('site.partner.recovery-wallet') }}" class="text-xs text-brand hover:underline">Open commission wallet →</a>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">Assigned</dt>
                        <dd class="font-medium">{{ $assignment->assigned_at?->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 text-xs">SLA due</dt>
                        <dd class="font-medium {{ $slaBreached ? 'text-red-700' : '' }}">
                            {{ $assignment->sla_due_at?->format('d M Y') ?? '—' }}
                            @if ($sla_days_remaining !== null && $isOpen)
                                <span class="text-xs text-gray-500">({{ $sla_days_remaining }} days left)</span>
                            @endif
                        </dd>
                    </div>
                </dl>
                @if ($assignment->notes)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Case notes</p>
                        <p class="text-sm whitespace-pre-line text-gray-700">{{ $assignment->notes }}</p>
                    </div>
                @endif
            </div>

            @if (($mini_schedule ?? collect())->isNotEmpty())
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">Upcoming installments</h2>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($mini_schedule as $row)
                            <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium">#{{ $row['installment_no'] }} · {{ optional($row['due_date'])->format('d M Y') }}</span>
                                <span class="font-semibold">{{ format_money($row['amount_due']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($collateral_items) || ! empty($show_gps_installer_contact))
                <x-collateral-gps-panel
                    :items="$collateral_items ?? []"
                    :installer-contact="$gps_installer_contact ?? null"
                    :show-installer-contact="(bool) ($show_gps_installer_contact ?? false)"
                    title="Collateral"
                    class="glass-card rounded-2xl ring-1 ring-brand/10 p-5"
                />
            @endif

            @if (! empty($talk_track['lines'] ?? null))
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">{{ $talk_track['title'] ?? 'Suggested talk track' }}</h2>
                    <ol class="list-decimal pl-5 space-y-2 text-sm text-gray-700">
                        @foreach ($talk_track['lines'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if ($isOpen)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 class="font-bold">Record action</h2>
                        @if ($assignment->status === 'assigned')
                            <form method="POST" action="{{ route('site.partner.recovery-case.start', $assignment) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-slate-800 text-white text-xs font-semibold px-3 py-2 hover:bg-slate-900">
                                    Start case
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="space-y-4">
                        @foreach ($portal_actions as $actionKey => $action)
                            @php
                                $needsFile = ! empty($action['accepts_file']);
                                $needsNotes = ($action['notes'] ?? null) === 'required';
                                $needsProceeds = ! empty($action['requires_auction_proceeds']);
                                $isResolve = ! empty($action['completes']);
                                $isAuctionSold = $actionKey === 'sold' && $needsProceeds;
                                $needsConfirm = $isResolve
                                    || in_array($actionKey, ['repossession_complete', 'sold', 'listed', 'removed', 'resolved'], true);
                                $confirmTitle = match ($actionKey) {
                                    'repossession_complete' => __('site.partner_portal.confirm.repossession_title'),
                                    'sold' => __('site.partner_portal.confirm.sold_title'),
                                    'listed' => __('site.partner_portal.confirm.listed_title'),
                                    'removed' => __('site.partner_portal.confirm.gps_removed_title'),
                                    'resolved' => __('site.partner_portal.confirm.resolved_title'),
                                    default => __('site.partner_portal.confirm.complete_title', ['action' => $action['label']]),
                                };
                                $confirmMessage = match ($actionKey) {
                                    'repossession_complete' => __('site.partner_portal.confirm.repossession_message'),
                                    'sold' => __('site.partner_portal.confirm.sold_message'),
                                    'listed' => __('site.partner_portal.confirm.listed_message'),
                                    'removed' => __('site.partner_portal.confirm.gps_removed_message'),
                                    'resolved' => __('site.partner_portal.confirm.resolved_message'),
                                    default => __('site.partner_portal.confirm.complete_message', ['action' => $action['label']]),
                                };
                                $confirmLabel = match ($actionKey) {
                                    'repossession_complete' => __('site.partner_portal.confirm.repossession_button'),
                                    'sold' => __('site.partner_portal.confirm.sold_button'),
                                    'listed' => __('site.partner_portal.confirm.listed_button'),
                                    'removed' => __('site.partner_portal.confirm.gps_removed_button'),
                                    'resolved' => __('site.partner_portal.confirm.resolved_button'),
                                    default => __('site.partner_portal.confirm.complete_button'),
                                };
                                $confirmTone = in_array($actionKey, ['repossession_complete', 'sold', 'removed'], true) ? 'warning' : 'confirm';
                            @endphp
                            <form method="POST"
                                  action="{{ route('site.partner.recovery-case.action', $assignment) }}"
                                  enctype="multipart/form-data"
                                  class="rounded-xl border border-gray-200 p-4 {{ $isResolve ? 'border-emerald-200 bg-emerald-50/40' : '' }}"
                                  @if ($needsConfirm)
                                      @submit.prevent="window.confirmForm($el, {
                                          title: @js($confirmTitle),
                                          message: @js($confirmMessage),
                                          confirmLabel: @js($confirmLabel),
                                          tone: @js($confirmTone),
                                          confirmClass: @js($confirmTone === 'warning' ? 'bg-amber-500 hover:bg-amber-400 text-gray-900' : 'bg-brand-gold hover:bg-yellow-400 text-brand'),
                                      })"
                                  @endif>
                                @csrf
                                <input type="hidden" name="action" value="{{ $actionKey }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $action['label'] }}</p>
                                        @if ($needsNotes)
                                            <p class="text-[11px] text-gray-500 mt-0.5">Notes required</p>
                                        @endif
                                    </div>
                                    <button type="submit"
                                            class="rounded-lg px-3 py-2 text-xs font-semibold {{ $isResolve ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-brand hover:bg-brand-light text-white' }}">
                                        Submit
                                    </button>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @if (! empty($action['requires_contact']))
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Who you contacted</label>
                                            <select name="contacted_party" required
                                                    class="w-full rounded-lg border-gray-300 text-sm">
                                                <option value="">Select borrower, guarantor, next of kin, or member</option>
                                                @foreach (($collection_contacts ?? []) as $party)
                                                    <option value="{{ $party['key'] }}">
                                                        {{ $party['role'] }} — {{ $party['name'] }}
                                                        @if (! empty($party['phone_label']))
                                                            · {{ $party['phone_label'] }}
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    @if ($needsProceeds)
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Auction proceeds (TZS)</label>
                                            <input type="number" name="auction_proceeds" step="0.01" min="0.01" required
                                                   placeholder="Amount received at auction"
                                                   class="w-full rounded-lg border-gray-300 text-sm font-mono">
                                            <p class="text-[11px] text-gray-500 mt-1">Loan balance and recovery costs are settled automatically. Surplus is returned to the borrower.</p>
                                        </div>
                                    @endif
                                    @if ($isAuctionSold)
                                        <div class="grid sm:grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Lot reference (optional)</label>
                                                <input type="text" name="lot_reference" maxlength="80"
                                                       class="w-full rounded-lg border-gray-300 text-sm"
                                                       placeholder="e.g. LOT-12">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">Buyer name (optional)</label>
                                                <input type="text" name="buyer_name" maxlength="120"
                                                       class="w-full rounded-lg border-gray-300 text-sm"
                                                       placeholder="Buyer / bidder">
                                            </div>
                                        </div>
                                    @endif
                                    <textarea name="notes" rows="2" maxlength="2000"
                                              @if ($needsNotes && ! $needsFile) required @endif
                                              placeholder="{{ $needsNotes ? 'Enter details…' : 'Optional notes…' }}"
                                              class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                                    @if ($needsFile)
                                        <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf"
                                               class="w-full text-sm rounded-lg border-gray-300">
                                    @endif
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-1">Activity on this assignment</h2>
                <p class="text-xs text-gray-500 mb-3">Shows actions logged for this partner case only.</p>
                @if (($activity ?? collect())->isEmpty())
                    <p class="text-sm text-gray-500">No actions logged yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($activity as $entry)
                            <li class="py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold">{{ ucfirst(str_replace('_', ' ', $entry->action_type)) }}</p>
                                    <p class="text-xs text-gray-500">{{ $entry->performed_at?->format('d M Y H:i') }}</p>
                                </div>
                                @if ($entry->result)
                                    <p class="text-xs text-gray-500 mt-0.5">Result: {{ ucfirst(str_replace('_', ' ', $entry->result)) }}</p>
                                @endif
                                @if ($entry->notes)
                                    <p class="text-xs text-gray-600 mt-1">{{ $entry->notes }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            @if ($assignment->vendorTask)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">Linked task</h2>
                    <p class="text-sm text-gray-600 mb-3">{{ ucfirst(str_replace('_', ' ', $assignment->vendorTask->task_type)) }}</p>
                    <a href="{{ route('site.partner.task', $assignment->vendorTask) }}"
                       class="inline-flex rounded-lg bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light">
                        Open task
                    </a>

                    @if ($assignment->vendorTask->documents->isNotEmpty())
                        <ul class="mt-4 divide-y divide-gray-100 text-sm">
                            @foreach ($assignment->vendorTask->documents as $document)
                                <li class="py-2 flex items-center justify-between gap-2">
                                    <span class="truncate">{{ $document->label }}</span>
                                    <x-site.document-view-button :url="asset('storage/'.$document->file_path)" label="View" class="text-brand text-xs hover:underline" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 text-sm text-gray-600">
                <p class="font-semibold text-gray-900 mb-2">Commission</p>
                <p class="mb-3">Commission is calculated from the original outstanding at assignment — not compounded across partners.</p>
                <a href="{{ $wallet_url ?? route('site.partner.recovery-wallet') }}" class="text-brand text-xs font-semibold hover:underline">
                    View wallet & payouts →
                </a>
            </div>
        </div>
    </div>
</x-site.vendor-layout>
