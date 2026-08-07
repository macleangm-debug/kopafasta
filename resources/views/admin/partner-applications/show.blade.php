<x-admin.layout
    :title="'Partner application · '.$application->full_name"
    heading=""
    :backUrl="route('admin.partner-applications.index')"
    backLabel="Back to applications">

    @php
        $applicant = $review['applicant'];
        $business = $review['business'];
        $decision = $review['decision'];
        $checklist = $review['checklist'];
        $identity = $review['identity'];
        $anomalies = $anomalies ?? [];
        $defaultTab = request('tab', 'applicant');
        $statusTone = match ($decision['status']) {
            'approved'   => 'bg-emerald-500/20 text-emerald-100 ring-emerald-300/40',
            'rejected'   => 'bg-red-500/20 text-red-100 ring-red-300/40',
            'needs_info' => 'bg-sky-500/20 text-sky-100 ring-sky-300/40',
            default      => 'bg-white/10 text-white ring-white/20',
        };
        $anomalyTone = [
            'critical' => 'bg-rose-50 ring-rose-200 text-rose-950',
            'warning' => 'bg-amber-50 ring-amber-200 text-amber-950',
            'info' => 'bg-sky-50 ring-sky-200 text-sky-950',
        ];
        $anomalyDot = [
            'critical' => 'bg-rose-500',
            'warning' => 'bg-amber-500',
            'info' => 'bg-sky-500',
        ];
    @endphp

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    {{-- Letterhead --}}
    <div class="mb-5 -mt-2 rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    <div class="shrink-0 rounded-xl bg-white/10 ring-1 ring-white/20 p-2.5">
                        <x-site.brand-mark size="sm" variant="light" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ brand_name() }} · Partner enrolment</p>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $applicant['full_name'] }}</h1>
                        <p class="text-sm text-white/75 mt-1 truncate">
                            {{ $applicant['category_label'] }}
                            <span class="text-white/50">·</span> {{ ucfirst($applicant['applicant_category']) }}
                            @if ($business['trading_name'])
                                <span class="text-white/50">·</span> {{ $business['trading_name'] }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ring-1 {{ $statusTone }}">
                        {{ ucfirst(str_replace('_', ' ', $decision['status'])) }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-gold/20 text-brand-gold ring-1 ring-brand-gold/40">
                        {{ $review['satisfied_docs'] }}/{{ $review['required_docs'] }} docs · {{ $review['checklist_progress'] }}%
                    </span>
                    @if ($decision['partner_id'])
                        <a href="{{ route('admin.partners.show', $decision['partner_id']) }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">
                            View partner {{ $decision['partner']?->vendor_number ?? '' }}
                        </a>
                    @endif
                </div>
            </div>
            <div class="mt-4">
                <div class="h-1.5 rounded-full bg-white/15 overflow-hidden">
                    <div class="h-full bg-brand-gold rounded-full transition-all" style="width: {{ $review['checklist_progress'] }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Decision guidance --}}
    @if (! empty($anomalies))
        <div class="mb-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Decision guidance</p>
                    <h3 class="text-sm font-bold text-gray-900 mt-0.5">{{ count($anomalies) }} flag{{ count($anomalies) === 1 ? '' : 's' }} to review first</h3>
                </div>
                <p class="text-[11px] text-gray-500">Checklist signals for faster enrolment decisions.</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($anomalies as $anomaly)
                    <li class="px-5 py-3 flex gap-3 {{ $anomalyTone[$anomaly['severity']] ?? 'bg-gray-50' }}">
                        <span class="mt-1.5 size-2 rounded-full shrink-0 {{ $anomalyDot[$anomaly['severity']] ?? 'bg-gray-400' }}"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $anomaly['title'] }}</p>
                            <p class="text-xs mt-0.5 opacity-80">{{ $anomaly['detail'] }}</p>
                        </div>
                        <span class="ml-auto shrink-0 text-[10px] uppercase tracking-wider font-semibold opacity-70">{{ $anomaly['severity'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid lg:grid-cols-12 gap-6" x-data="{ tab: @js($defaultTab) }">
        <div class="lg:col-span-8 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-4 sm:px-5 pt-4 flex flex-wrap gap-1.5 border-b border-gray-100">
                    @foreach ([
                        'applicant' => 'Applicant',
                        'business' => 'Business',
                        'identity' => 'Identity',
                        'documents' => 'Documents',
                    ] as $key => $label)
                        <button type="button"
                                @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'bg-brand text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                                class="rounded-t-lg px-3.5 py-2 text-xs font-semibold transition">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="p-5 sm:p-6" x-show="tab === 'applicant'" x-cloak>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">Applicant</p>
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Contact name</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ $applicant['full_name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Applicant type</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ ucfirst($applicant['applicant_category']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Email</dt>
                            <dd class="mt-0.5">{{ $applicant['email'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Phone</dt>
                            <dd class="mt-0.5">{{ $applicant['phone'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Partner category</dt>
                            <dd class="font-medium mt-0.5">{{ $applicant['category_label'] }}</dd>
                        </div>
                        @if (($applicant['category'] ?? null) === 'debt_collector')
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-gray-500">Service capabilities</dt>
                                <dd class="mt-1 flex flex-wrap gap-2">
                                    @foreach ($applicant['requested_roles'] ?? ['debt_collector'] as $role)
                                        <span class="inline-flex items-center rounded-full bg-brand-muted px-2.5 py-1 text-xs font-semibold text-brand ring-1 ring-brand/15">
                                            {{ $role === 'auctioneer' ? 'Auctioning' : 'Repossession' }}
                                        </span>
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs text-gray-500">Primary region</dt>
                            <dd class="mt-0.5">{{ $applicant['region'] ?: '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-gray-500">Coverage regions</dt>
                            <dd class="mt-0.5">{{ $applicant['coverage_regions'] ? implode(', ', $applicant['coverage_regions']) : '—' }}</dd>
                        </div>
                    </dl>
                    @if ($applicant['message'])
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-xs text-gray-500 mb-1">Message from applicant</p>
                            <p class="text-sm text-gray-800 whitespace-pre-line">{{ $applicant['message'] }}</p>
                        </div>
                    @endif
                </div>

                <div class="p-5 sm:p-6" x-show="tab === 'business'" x-cloak>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">Business</p>
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Trading name</dt>
                            <dd class="font-medium mt-0.5">{{ $business['trading_name'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Legal name</dt>
                            <dd class="font-medium mt-0.5">{{ $business['legal_name'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Registration / BRELA</dt>
                            <dd class="mt-0.5">{{ $business['registration_number'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">TIN</dt>
                            <dd class="mt-0.5">{{ $business['tin'] ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="p-5 sm:p-6" x-show="tab === 'identity'" x-cloak>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">Identity</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        @foreach (['national_id_front' => 'National ID (front)', 'national_id_back' => 'National ID (back)'] as $key => $label)
                            @php $doc = $identity[$key] ?? null; @endphp
                            <div class="rounded-xl ring-1 ring-gray-200 p-4">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">{{ $label }}</p>
                                @if ($doc)
                                    <button type="button"
                                            onclick="window.kfOpenDocumentPreview(@js($doc['url']), @js($label), @js($doc['is_image'] ? 'image' : 'pdf'))"
                                            class="block w-full text-left group mt-3">
                                        @if ($doc['is_image'])
                                            <img src="{{ $doc['url'] }}" alt="{{ $label }}"
                                                 class="max-h-40 rounded-lg object-cover ring-1 ring-gray-200 group-hover:ring-brand-gold transition cursor-zoom-in">
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold rounded-lg bg-gray-100 px-3 py-2">📄 {{ $doc['original_name'] ?: 'View document' }}</span>
                                        @endif
                                        <span class="text-xs font-semibold text-brand mt-2 inline-block">Preview</span>
                                    </button>
                                @else
                                    <p class="text-sm text-gray-500 mt-3">Not uploaded.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="p-5 sm:p-6" x-show="tab === 'documents'" x-cloak>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">Documents & checklist</p>
                    <div class="grid sm:grid-cols-2 gap-2 mb-5">
                        @forelse ($checklist as $item)
                            <div class="flex items-center justify-between gap-3 rounded-lg ring-1 px-3 py-2.5 text-sm {{ $item['present'] ? 'bg-emerald-50 ring-emerald-200 text-emerald-900' : 'bg-amber-50 ring-amber-200 text-amber-900' }}">
                                <span class="font-medium">{{ $item['label'] }}</span>
                                <span class="text-[10px] font-semibold rounded-full px-2 py-0.5 {{ $item['present'] ? 'bg-emerald-100' : 'bg-amber-100' }}">
                                    {{ $item['present'] ? 'On file' : 'Missing' }}
                                </span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 sm:col-span-2">No specific documents are required for this category.</p>
                        @endforelse
                    </div>
                    @if (empty($review['documents']))
                        <p class="text-sm text-gray-500">No documents uploaded.</p>
                    @else
                        <div class="grid sm:grid-cols-2 gap-4">
                            @foreach ($review['documents'] as $doc)
                                <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden">
                                    <div class="px-3 py-2 border-b border-gray-100">
                                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $doc['label'] }}</p>
                                        <p class="text-[11px] text-gray-500 truncate">{{ $doc['original_name'] }}</p>
                                    </div>
                                    <div class="p-3">
                                        <button type="button"
                                                onclick="window.kfOpenDocumentPreview(@js($doc['url']), @js($doc['label']), @js($doc['is_image'] ? 'image' : 'pdf'))"
                                                class="block w-full text-left group">
                                            @if ($doc['is_image'])
                                                <img src="{{ $doc['url'] }}" alt="{{ $doc['label'] }}"
                                                     class="w-full h-28 rounded-lg object-cover ring-1 ring-gray-200 group-hover:ring-brand-gold transition cursor-zoom-in">
                                            @else
                                                <span class="flex h-28 items-center justify-center rounded-lg bg-gray-50 text-xs font-semibold text-gray-600 ring-1 ring-gray-200">PDF document</span>
                                            @endif
                                            <span class="text-xs font-semibold text-brand mt-2 inline-block">Preview</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Decision panel --}}
        <div class="lg:col-span-4 space-y-4">
            <div class="rounded-2xl shadow-sm overflow-hidden ring-2 ring-brand/25 bg-gradient-to-b from-brand-muted/50 to-white lg:sticky lg:top-4">
                <div class="bg-brand px-5 py-4 text-white">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-brand-gold">Decision</h2>
                    <p class="text-sm text-white/80 mt-1">Approve creates the partner account and code.</p>
                </div>
                <form method="POST" action="{{ route('admin.partner-applications.update', $application) }}" class="p-5 space-y-4"
                      x-data="{ status: @js($decision['status']) }"
                      @submit.prevent="window.confirmForm($el, {
                          title: status === 'approved' ? 'Approve this partner?' : (status === 'rejected' ? 'Reject this application?' : (status === 'needs_info' ? 'Request more information?' : 'Save decision?')),
                          message: status === 'approved'
                              ? 'This will create their partner account and partner code for activation (no SMS).'
                              : (status === 'needs_info'
                                  ? 'The applicant will see your notes on the tracking page.'
                                  : 'Confirm you want to save this decision.'),
                          confirmLabel: 'Yes, save',
                          confirmClass: 'bg-brand hover:bg-brand-light text-white',
                          tone: 'confirm',
                      })">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">Status</label>
                        <select x-model="status" name="status" class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand">
                            @foreach (['pending', 'needs_info', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected($decision['status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="status === 'rejected'" x-cloak>
                        <label class="block text-xs font-semibold text-brand mb-1">Rejection reason</label>
                        <select name="rejection_reason" class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand">
                            <option value="">— Select reason —</option>
                            @foreach ($review['rejection_reason_codes'] as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">Notes</label>
                        <textarea name="admin_notes" rows="4"
                                  x-bind:placeholder="status === 'needs_info' ? 'What should the applicant fix?' : 'Optional internal notes…'"
                                  class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand">{{ old('admin_notes', $decision['admin_notes']) }}</textarea>
                    </div>
                    @if ($decision['partner_id'])
                        <div class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3 text-sm">
                            <p class="text-xs uppercase tracking-widest text-brand font-semibold">Linked partner</p>
                            <a href="{{ route('admin.partners.show', $decision['partner_id']) }}" class="mt-1 inline-block font-bold text-brand hover:underline">
                                {{ $decision['partner']?->vendor_number ?? '#'.$decision['partner_id'] }}
                            </a>
                            <p class="text-xs text-gray-500 mt-1 capitalize">Status: {{ $decision['partner']?->status ?? '—' }}
                                @if ($decision['partner']?->activated_at)
                                    · Activated
                                @else
                                    · Awaiting activation
                                @endif
                            </p>
                        </div>
                    @endif
                    <button class="w-full bg-brand hover:bg-brand-light text-white font-semibold rounded-xl px-4 py-3 text-sm shadow-sm">Save decision</button>
                </form>
            </div>

            @if ($decision['reviewer'] || $decision['reviewed_at'])
                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5 text-sm">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-2">Last review</p>
                    <p class="text-gray-700">
                        <span class="font-semibold">{{ $decision['reviewer']?->name ?? '—' }}</span>
                        @if ($decision['reviewed_at'])
                            · {{ $decision['reviewed_at']->format('d M Y H:i') }}
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-admin.layout>
