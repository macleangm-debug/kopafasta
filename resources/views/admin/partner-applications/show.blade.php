<x-admin.layout
    :title="'Partner application · '.$application->full_name"
    heading=""
    :backUrl="route('admin.partner-applications.index', array_filter(['type' => ($applicant['category'] ?? '') === 'affiliate' ? 'affiliate' : null]))"
    backLabel="Back to applications">

    @php
        $applicant = $review['applicant'];
        $business = $review['business'];
        $decision = $review['decision'];
        $checklist = $review['checklist'];
        $identity = $review['identity'];
        $detail = $review['application_detail'] ?? [];
        $declaration = $review['declaration'] ?? [];
        $commercial = $review['commercial'] ?? [];
        $activity = $review['activity'] ?? [];
        $anomalies = $anomalies ?? [];
        $isAffiliate = ($applicant['category'] ?? '') === 'affiliate';
        $defaultTab = request('tab', 'overview');
        $tabs = [
            'overview' => 'Overview',
            'application' => 'Application',
            'identity' => 'Identity & Documents',
            'commercial' => 'Commercial',
            'activity' => 'Activity',
        ];
        $statusTone = match ($decision['status']) {
            'approved'   => 'bg-emerald-500/20 text-emerald-100 ring-emerald-300/40',
            'rejected'   => 'bg-red-500/20 text-red-100 ring-red-300/40',
            'needs_info' => 'bg-sky-500/20 text-sky-100 ring-sky-300/40',
            default      => 'bg-white/10 text-white ring-white/20',
        };
        $statusLabel = match ($decision['status']) {
            'pending' => 'Under review',
            'needs_info' => 'Needs information',
            'approved' => 'Approved',
            'rejected' => 'Declined',
            'awaiting_fee' => 'Awaiting fee',
            default => ucfirst(str_replace('_', ' ', (string) $decision['status'])),
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
        $genderLabel = match ($applicant['gender'] ?? null) {
            'male' => 'Male',
            'female' => 'Female',
            default => $applicant['gender'] ?: '—',
        };
        $fmtList = function ($items) {
            if (! is_array($items) || $items === []) {
                return '—';
            }

            return collect($items)->map(fn ($v) => ucfirst(str_replace('_', ' ', (string) $v)))->implode(', ');
        };
    @endphp

    {{-- Partner / Affiliate 360 hero --}}
    <div class="mb-5 -mt-2 rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    <div class="shrink-0 size-14 rounded-2xl bg-white/10 ring-1 ring-white/25 flex items-center justify-center text-xl font-bold text-brand-gold">
                        {{ strtoupper(mb_substr($applicant['full_name'] ?: 'P', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ brand_name() }} · Partner 360</p>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $applicant['full_name'] }}</h1>
                        <p class="text-sm text-white/75 mt-1 truncate">
                            {{ $applicant['reference'] }}
                            <span class="text-white/50">·</span> {{ $applicant['category_label'] }}
                            <span class="text-white/50">·</span> {{ ucfirst($applicant['applicant_category']) }}
                        </p>
                        <p class="text-xs text-white/65 mt-1">
                            Submitted {{ optional($applicant['submitted_at'])->format('d M Y') ?: '—' }}
                            @if ($applicant['phone'])
                                <span class="text-white/40">·</span> {{ $applicant['phone'] }}
                            @endif
                            @if ($applicant['email'])
                                <span class="text-white/40">·</span> {{ $applicant['email'] }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ring-1 {{ $statusTone }}">
                        {{ $statusLabel }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-gold/20 text-brand-gold ring-1 ring-brand-gold/40">
                        {{ $review['satisfied_docs'] }}/{{ $review['required_docs'] }} docs · {{ $review['checklist_progress'] }}%
                    </span>
                    @if ($decision['partner_id'])
                        <a href="{{ route('admin.partners.show', $decision['partner_id']) }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">
                            Open Affiliate 360 {{ $decision['partner']?->vendor_number ?? '' }}
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

    @if (! empty($anomalies))
        <div class="mb-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Needs attention</p>
                    <h3 class="text-sm font-bold text-gray-900 mt-0.5">{{ count($anomalies) }} flag{{ count($anomalies) === 1 ? '' : 's' }} to review first</h3>
                </div>
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
                    @foreach ($tabs as $key => $label)
                        <button type="button"
                                @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'bg-brand text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                                class="rounded-t-lg px-3.5 py-2 text-xs font-semibold transition">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Overview --}}
                <div class="p-5 sm:p-6 space-y-5" x-show="tab === 'overview'" x-cloak>
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Status</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ $statusLabel }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Submitted</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ optional($applicant['submitted_at'])->format('d M Y, H:i') ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Contact</dt>
                            <dd class="mt-0.5">{{ $applicant['phone'] }} @if($applicant['email']) · {{ $applicant['email'] }} @endif</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Location</dt>
                            <dd class="mt-0.5">
                                {{ collect([$applicant['street'] ?? null, $applicant['ward'] ?? null, $applicant['district'] ?? null, $applicant['region'] ?? null])->filter()->implode(', ') ?: '—' }}
                            </dd>
                        </div>
                        @if ($isAffiliate)
                            <div>
                                <dt class="text-xs text-gray-500">Occupation</dt>
                                <dd class="mt-0.5">{{ $detail['occupation'] ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Acquisition source</dt>
                                <dd class="mt-0.5">{{ $detail['how_heard'] ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Promotion channels</dt>
                                <dd class="mt-0.5">{{ $fmtList($detail['channels'] ?? []) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Estimated monthly reach</dt>
                                <dd class="mt-0.5">{{ $detail['monthly_reach'] ?: '—' }}</dd>
                            </div>
                            @if (($detail['has_social_profile'] ?? '') === 'yes')
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-gray-500">Social page</dt>
                                    <dd class="mt-0.5">
                                        {{ $detail['social_platform'] ?: '—' }}
                                        @if (! empty($detail['social_profile_url']))
                                            · <a href="{{ $detail['social_profile_url'] }}" target="_blank" rel="noopener" class="text-brand font-semibold hover:underline">{{ $detail['social_profile_url'] }}</a>
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        @endif
                        <div>
                            <dt class="text-xs text-gray-500">Review progress</dt>
                            <dd class="mt-0.5">{{ $review['checklist_progress'] }}% docs · {{ $review['satisfied_docs'] }}/{{ $review['required_docs'] }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Application answers --}}
                <div class="p-5 sm:p-6 space-y-6" x-show="tab === 'application'" x-cloak>
                    <section class="space-y-3">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">About you</p>
                        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                            <div><dt class="text-xs text-gray-500">Full legal name</dt><dd class="font-medium mt-0.5">{{ $applicant['full_name'] }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Date of birth</dt><dd class="mt-0.5">{{ $applicant['date_of_birth'] ? \Illuminate\Support\Carbon::parse($applicant['date_of_birth'])->format('d M Y') : '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Gender</dt><dd class="mt-0.5">{{ $genderLabel }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Applicant type</dt><dd class="mt-0.5">{{ ucfirst($applicant['applicant_category']) }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Email</dt><dd class="mt-0.5">{{ $applicant['email'] ?: '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Phone</dt><dd class="mt-0.5">{{ $applicant['phone'] }}@if($applicant['phone_alt']) · alt {{ $applicant['phone_alt'] }}@endif</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Address</dt><dd class="mt-0.5">{{ collect([$applicant['street'] ?? null, $applicant['ward'] ?? null, $applicant['district'] ?? null, $applicant['region'] ?? null])->filter()->implode(', ') ?: '—' }}</dd></div>
                        </dl>
                    </section>

                    <section class="space-y-3 border-t border-gray-100 pt-5">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Experience</p>
                        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                            <div><dt class="text-xs text-gray-500">Occupation</dt><dd class="mt-0.5">{{ $detail['occupation'] ?: '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Languages</dt><dd class="mt-0.5">{{ $fmtList($detail['languages'] ?? []) }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Sales experience</dt><dd class="mt-0.5 whitespace-pre-line">{{ $detail['sales_experience'] ?: '—' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Financial services experience</dt><dd class="mt-0.5 whitespace-pre-line">{{ $detail['financial_services_experience'] ?: '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Previous agent</dt><dd class="mt-0.5">{{ ucfirst((string) ($detail['previous_agent'] ?? '—')) }}</dd></div>
                            @if (($detail['previous_agent'] ?? '') === 'yes')
                                <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Previous role details</dt><dd class="mt-0.5 whitespace-pre-line">{{ $detail['previous_agent_details'] ?: '—' }}</dd></div>
                            @endif
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Why Affiliate</dt><dd class="mt-0.5 whitespace-pre-line">{{ $detail['why_affiliate'] ?: '—' }}</dd></div>
                        </dl>
                    </section>

                    <section class="space-y-3 border-t border-gray-100 pt-5">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Your market</p>
                        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500">How they will find customers</dt><dd class="mt-0.5">{{ $fmtList($detail['acquisition_methods'] ?? []) }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Channels</dt><dd class="mt-0.5">{{ $fmtList($detail['channels'] ?? []) }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Monthly reach</dt><dd class="mt-0.5">{{ $detail['monthly_reach'] ?: '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">How heard</dt><dd class="mt-0.5">{{ $detail['how_heard'] ?: '—' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500">First 10 customers plan</dt><dd class="mt-0.5 whitespace-pre-line">{{ $detail['first_10_customers'] ?: '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Registered business</dt><dd class="mt-0.5">{{ ucfirst((string) ($detail['registered_business'] ?? '—')) }}</dd></div>
                            @if (($detail['has_social_profile'] ?? '') === 'yes')
                                <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Social</dt><dd class="mt-0.5">{{ $detail['social_platform'] ?: '—' }} · {{ $detail['social_profile_url'] ?: '—' }}</dd></div>
                            @endif
                        </dl>
                    </section>

                    <section class="space-y-3 border-t border-gray-100 pt-5">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Declaration</p>
                        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                            <div><dt class="text-xs text-gray-500">Accepted</dt><dd class="mt-0.5">{{ ($declaration['accepted'] ?? false) ? 'Yes' : 'No' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Version</dt><dd class="mt-0.5 font-mono text-xs">{{ $declaration['version'] ?: '—' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Accepted at</dt><dd class="mt-0.5">{{ $declaration['accepted_at'] ? \Illuminate\Support\Carbon::parse($declaration['accepted_at'])->format('d M Y H:i') : '—' }}</dd></div>
                        </dl>
                    </section>
                </div>

                {{-- Identity & Documents --}}
                <div class="p-5 sm:p-6 space-y-5" x-show="tab === 'identity'" x-cloak>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">National ID (NIDA)</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        @foreach (['national_id_front' => 'NIDA Front', 'national_id_back' => 'NIDA Back'] as $key => $label)
                            @php $doc = $identity[$key] ?? null; @endphp
                            <div class="rounded-xl ring-1 ring-gray-200 p-4">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">{{ $label }}</p>
                                @if ($doc)
                                    <button type="button"
                                            onclick="window.kfOpenDocumentPreview(@js($doc['url']), @js($label), @js($doc['is_image'] ? 'image' : 'pdf'))"
                                            class="block w-full text-left group mt-3">
                                        @if ($doc['is_image'])
                                            <img src="{{ $doc['url'] }}" alt="{{ $label }}"
                                                 class="max-h-48 w-full rounded-lg object-cover ring-1 ring-gray-200 group-hover:ring-brand-gold transition cursor-zoom-in">
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold rounded-lg bg-gray-100 px-3 py-2">{{ $doc['original_name'] ?: 'View document' }}</span>
                                        @endif
                                        <span class="text-xs font-semibold text-brand mt-2 inline-block">Preview</span>
                                    </button>
                                @else
                                    <p class="text-sm text-gray-500 mt-3">Not uploaded.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-100 pt-5">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">Document checklist</p>
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
                        @if (! empty($review['documents']))
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

                    <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 text-sm">
                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">Declaration audit</p>
                        <p class="mt-2 text-gray-800">
                            {{ ($declaration['accepted'] ?? false) ? 'Accepted' : 'Not accepted' }}
                            @if (! empty($declaration['version']))
                                · <span class="font-mono text-xs">{{ $declaration['version'] }}</span>
                            @endif
                            @if (! empty($declaration['accepted_at']))
                                · {{ \Illuminate\Support\Carbon::parse($declaration['accepted_at'])->format('d M Y H:i') }}
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Commercial --}}
                <div class="p-5 sm:p-6 space-y-4" x-show="tab === 'commercial'" x-cloak>
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Proposed Affiliate type</dt>
                            <dd class="font-medium mt-0.5">{{ $commercial['proposed_type'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Territory</dt>
                            <dd class="mt-0.5">{{ $commercial['territory'] ?? '—' }}</dd>
                        </div>
                        @if ($isAffiliate)
                            <div>
                                <dt class="text-xs text-gray-500">Application fee</dt>
                                <dd class="mt-0.5">
                                    @if ($commercial['application_fee_required'] ?? false)
                                        {{ format_money((float) ($commercial['application_fee_amount'] ?? 0)) }}
                                    @else
                                        Not required
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Fee status</dt>
                                <dd class="mt-0.5">{{ $application->status === 'awaiting_fee' ? 'Awaiting payment' : 'Paid or not required for review' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-gray-500">Default commercial configuration</dt>
                                <dd class="mt-0.5 text-gray-600">Ordinary Affiliate terms from Settings Hub apply after approval. Agreement acceptance still required before Share &amp; Earn.</dd>
                            </div>
                        @endif
                        @if ($business['trading_name'] || $business['legal_name'] || $business['registration_number'] || $business['tin'])
                            <div><dt class="text-xs text-gray-500">Trading name</dt><dd class="mt-0.5">{{ $business['trading_name'] ?: '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Legal name</dt><dd class="mt-0.5">{{ $business['legal_name'] ?: '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Registration</dt><dd class="mt-0.5">{{ $business['registration_number'] ?: '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">TIN</dt><dd class="mt-0.5">{{ $business['tin'] ?: '—' }}</dd></div>
                        @endif
                    </dl>
                    @if ($decision['partner_id'])
                        <a href="{{ route('admin.partners.show', ['partner' => $decision['partner_id'], 'tab' => 'commercial']) }}"
                           class="inline-flex text-sm font-semibold text-brand hover:underline">
                            Open operational commercial view →
                        </a>
                    @endif
                </div>

                {{-- Activity --}}
                <div class="p-5 sm:p-6" x-show="tab === 'activity'" x-cloak>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-3">Timeline</p>
                    @if (empty($activity))
                        <p class="text-sm text-gray-500">No activity recorded yet.</p>
                    @else
                        <ol class="space-y-3">
                            @foreach ($activity as $event)
                                <li class="rounded-xl ring-1 ring-gray-200 px-4 py-3">
                                    <p class="text-sm font-semibold text-gray-900">{{ $event['label'] }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        @if ($event['at'] instanceof \Illuminate\Support\Carbon)
                                            {{ $event['at']->format('d M Y H:i') }}
                                        @elseif (filled($event['at'] ?? null))
                                            {{ \Illuminate\Support\Carbon::parse($event['at'])->format('d M Y H:i') }}
                                        @else
                                            —
                                        @endif
                                    </p>
                                    @if (! empty($event['detail']))
                                        <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $event['detail'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </div>

        {{-- Decision panel — Action → Review → Confirm → Execute --}}
        <div class="lg:col-span-4 space-y-4">
            <div class="rounded-2xl shadow-sm overflow-hidden ring-2 ring-brand/25 bg-gradient-to-b from-brand-muted/50 to-white lg:sticky lg:top-4">
                <div class="bg-brand px-5 py-4 text-white">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-brand-gold">Review decision</h2>
                    <p class="text-sm text-white/80 mt-1">Approve · Request information · Decline</p>
                </div>
                <form method="POST" action="{{ route('admin.partner-applications.update', $application) }}" class="p-5 space-y-4"
                      x-data="{ status: @js($decision['status']) }"
                      @submit.prevent="window.confirmForm($el, {
                          title: status === 'approved'
                              ? 'Approve this Affiliate application?'
                              : (status === 'rejected'
                                  ? 'Decline this application?'
                                  : (status === 'needs_info'
                                      ? 'Request more information?'
                                      : 'Save decision?')),
                          message: status === 'approved'
                              ? 'This will create/activate their ordinary Affiliate account using existing enrollment infrastructure. Territory and default commercial Settings apply. They must still accept the Affiliate Agreement before Share & Earn. Paying the fee does not approve them — this decision does.'
                              : (status === 'needs_info'
                                  ? 'The applicant will see your notes on the tracking page and can update their application. They will be notified through existing Communications where available.'
                                  : (status === 'rejected'
                                      ? 'The applicant will see this decline on the tracking page. Include a controlled reason and description.'
                                      : 'Confirm you want to save this decision.')),
                          confirmLabel: status === 'approved' ? 'Approve Affiliate' : (status === 'rejected' ? 'Decline application' : (status === 'needs_info' ? 'Request information' : 'Yes, save')),
                          confirmClass: 'bg-brand hover:bg-brand-light text-white',
                          tone: 'confirm',
                      })">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">Action</label>
                        <select x-model="status" name="status" class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand">
                            <option value="pending" @selected($decision['status'] === 'pending')>Under review</option>
                            <option value="needs_info" @selected($decision['status'] === 'needs_info')>Request information</option>
                            <option value="approved" @selected($decision['status'] === 'approved')>Approve</option>
                            <option value="rejected" @selected($decision['status'] === 'rejected')>Decline</option>
                        </select>
                    </div>
                    <div x-show="status === 'rejected'" x-cloak>
                        <label class="block text-xs font-semibold text-brand mb-1">Decline reason</label>
                        <select name="rejection_reason" class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand"
                                :required="status === 'rejected'">
                            <option value="">— Select reason —</option>
                            @foreach ($review['rejection_reason_codes'] as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">
                            <span x-text="status === 'needs_info' ? 'What is required from the applicant?' : (status === 'rejected' ? 'Description' : 'Notes')"></span>
                        </label>
                        <textarea name="admin_notes" rows="4"
                                  x-bind:required="status === 'needs_info' || status === 'rejected'"
                                  x-bind:placeholder="status === 'needs_info' ? 'Specify exactly what documents or details are needed…' : (status === 'rejected' ? 'Describe the decline for the applicant…' : 'Optional internal notes…')"
                                  class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand">{{ old('admin_notes', $decision['admin_notes']) }}</textarea>
                    </div>
                    @if ($decision['partner_id'])
                        <div class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3 text-sm">
                            <p class="text-xs uppercase tracking-widest text-brand font-semibold">Linked Affiliate</p>
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
                    <button class="w-full bg-brand hover:bg-brand-light text-white font-semibold rounded-xl px-4 py-3 text-sm shadow-sm"
                            x-text="status === 'approved' ? 'Review & approve' : (status === 'rejected' ? 'Review & decline' : (status === 'needs_info' ? 'Review & request info' : 'Save'))">
                        Save decision
                    </button>
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
