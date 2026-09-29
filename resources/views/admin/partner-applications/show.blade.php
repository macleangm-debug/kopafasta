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
        $performance = $performance ?? null;
        $isAffiliate = ($applicant['category'] ?? '') === 'affiliate';
        $defaultTab = request('tab', 'overview');
        $tabs = [
            'overview' => 'Overview',
            'application' => 'Application',
            'identity' => 'Identity & Documents',
            'commercial' => 'Commercial',
            'activity' => 'Activity',
        ];
        if (! empty($performance) && $isAffiliate) {
            $tabs['performance'] = 'Performance';
        }
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
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand bg-brand-gold px-3 py-1.5 rounded-lg">
                            {{ $decision['partner']?->vendor_number ?? $decision['partner']?->partner_number ?? 'Partner' }}
                        </span>
                        <a href="{{ route('admin.partners.show', ['vendor' => $decision['partner_id'], 'operational' => 1]) }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-white/90 ring-1 ring-white/30 hover:bg-white/10 px-3 py-1.5 rounded-lg">
                            Operational tools
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

    @php
        $partnerMatches = $partnerMatches ?? [];
        $unresolvedPartnerMatches = collect($partnerMatches)->filter(fn ($m) => ! ($m['resolved'] ?? false))->values()->all();
        $hasUnresolvedPartnerMatch = $unresolvedPartnerMatches !== [];
    @endphp

    @if (! empty($anomalies))
        @php
            // Match collisions live only in Review Decision → Review match (no header duplicate).
            $attentionAnomalies = collect($anomalies)
                ->reject(fn ($a) => in_array(($a['code'] ?? ''), ['existing_partner', 'existing_partner_resolved'], true))
                ->values()
                ->all();
        @endphp
        @if ($attentionAnomalies !== [])
        <div class="mb-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Needs attention</p>
                    <h3 class="text-sm font-bold text-gray-900 mt-0.5">{{ count($attentionAnomalies) }} flag{{ count($attentionAnomalies) === 1 ? '' : 's' }} to review first</h3>
                </div>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($attentionAnomalies as $anomaly)
                    <li class="px-5 py-3 flex gap-3 {{ $anomalyTone[$anomaly['severity']] ?? 'bg-gray-50' }}">
                        <span class="mt-1.5 size-2 rounded-full shrink-0 {{ $anomalyDot[$anomaly['severity']] ?? 'bg-gray-400' }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $anomaly['title'] }}</p>
                            <p class="text-xs mt-0.5 opacity-80">{{ $anomaly['detail'] }}</p>
                        </div>
                        <span class="ml-auto shrink-0 text-[10px] uppercase tracking-wider font-semibold opacity-70">{{ $anomaly['severity'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
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

                {{-- Identity & Documents (Member 360 sizing: compact National ID Front|Back once) --}}
                <div class="p-5 sm:p-6 space-y-5" x-show="tab === 'identity'" x-cloak>
                    @php
                        $infoRequests = $review['info_requests'] ?? [];
                        $otherDocs = collect($review['documents'] ?? [])
                            ->reject(fn ($doc) => in_array($doc['doc_type'] ?? '', ['national_id_front', 'national_id_back'], true))
                            ->values();
                    @endphp

                    <section>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">National ID</p>
                        <div class="grid grid-cols-2 gap-3 max-w-md">
                            @foreach (['national_id_front' => 'Front', 'national_id_back' => 'Back'] as $key => $sideLabel)
                                @php $doc = $identity[$key] ?? null; @endphp
                                <div class="rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
                                    <p class="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100">{{ $sideLabel }}</p>
                                    @if ($doc && ! empty($doc['url']))
                                        <button type="button"
                                                onclick="window.kfOpenDocumentPreview(@js($doc['url']), @js('National ID — '.$sideLabel), @js(($doc['is_image'] ?? false) ? 'image' : 'pdf'))"
                                                class="block w-full text-left">
                                            <div class="aspect-[3/2] bg-gray-50">
                                                @if ($doc['is_image'] ?? false)
                                                    <img src="{{ $doc['url'] }}" alt="National ID {{ $sideLabel }}" class="size-full object-cover">
                                                @else
                                                    <div class="size-full grid place-items-center text-xs font-semibold text-gray-500">PDF</div>
                                                @endif
                                            </div>
                                        </button>
                                    @else
                                        <div class="aspect-[3/2] grid place-items-center bg-gray-50">
                                            <p class="text-xs text-gray-400">Missing</p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @if ($otherDocs->isNotEmpty() || ! empty($infoRequests))
                        <section class="border-t border-gray-100 pt-5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">Other documents</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-w-2xl">
                                @foreach ($otherDocs as $doc)
                                    <div class="rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
                                        <p class="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100 truncate">{{ $doc['label'] }}</p>
                                        <button type="button"
                                                onclick="window.kfOpenDocumentPreview(@js($doc['url']), @js($doc['label']), @js(($doc['is_image'] ?? false) ? 'image' : 'pdf'))"
                                                class="block w-full text-left">
                                            <div class="aspect-[3/2] bg-gray-50">
                                                @if ($doc['is_image'] ?? false)
                                                    <img src="{{ $doc['url'] }}" alt="{{ $doc['label'] }}" class="size-full object-cover">
                                                @else
                                                    <div class="size-full grid place-items-center text-xs font-semibold text-gray-500">PDF</div>
                                                @endif
                                            </div>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            @if (! empty($infoRequests))
                                <ul class="mt-4 space-y-2">
                                    @foreach ($infoRequests as $req)
                                        <li class="flex items-center justify-between gap-3 rounded-lg ring-1 ring-gray-200 px-3 py-2 text-sm">
                                            <span class="font-medium text-gray-800">{{ $req['label'] ?? 'Request' }}</span>
                                            <span class="text-[10px] font-semibold rounded-full px-2 py-0.5 {{ ($req['status'] ?? '') === 'submitted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                                {{ ($req['status'] ?? '') === 'submitted' ? 'Submitted' : 'Requested' }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endif
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
                        <a href="{{ route('admin.partners.show', ['vendor' => $decision['partner_id'], 'tab' => 'commercial', 'operational' => 1]) }}"
                           class="inline-flex text-sm font-semibold text-brand hover:underline">
                            Open operational commercial tools →
                        </a>
                    @endif
                </div>

                {{-- Performance (existing Affiliate metrics only; no invented KPIs) --}}
                @if (! empty($performance) && $isAffiliate)
                    <div class="p-5 sm:p-6 space-y-4" x-show="tab === 'performance'" x-cloak>
                        <p class="text-xs text-gray-500">Existing Affiliate activity — Standard KPI rules remain Settings-governed; Premium has no target enforcement here.</p>
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div class="rounded-xl ring-1 ring-gray-200 px-4 py-3">
                                <dt class="text-xs text-gray-500">Clicks</dt>
                                <dd class="mt-1 text-xl font-bold text-brand">{{ number_format((int) ($performance['clicks'] ?? 0)) }}</dd>
                            </div>
                            <div class="rounded-xl ring-1 ring-gray-200 px-4 py-3">
                                <dt class="text-xs text-gray-500">Registrations</dt>
                                <dd class="mt-1 text-xl font-bold text-brand">{{ number_format((int) ($performance['registrations'] ?? 0)) }}</dd>
                            </div>
                            <div class="rounded-xl ring-1 ring-gray-200 px-4 py-3">
                                <dt class="text-xs text-gray-500">Applications</dt>
                                <dd class="mt-1 text-xl font-bold text-brand">{{ number_format((int) ($performance['applications'] ?? 0)) }}</dd>
                            </div>
                            <div class="rounded-xl ring-1 ring-gray-200 px-4 py-3">
                                <dt class="text-xs text-gray-500">Commission earned</dt>
                                <dd class="mt-1 text-xl font-bold text-brand">{{ format_money((float) ($performance['commissions'] ?? 0)) }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

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

        {{-- Decision panel — Action → Review → Confirm → Execute (same surface) --}}
        <div class="lg:col-span-4 space-y-4"
             x-data="partnerMatchResolution(@js([
                'matches' => $partnerMatches,
                'resolveUrl' => route('admin.partner-applications.match-resolution', $application),
             ]))"
             @open-partner-match.window="openMatch(0)">
            @php
                $requestCatalog = $requestCatalog ?? ['documents' => [], 'information' => [], 'replace_reasons' => []];
                $existingDocumentOptions = $existingDocumentOptions ?? [];
                $existingPartnerMatch = $hasUnresolvedPartnerMatch
                    || collect($anomalies ?? [])->contains(fn ($a) => ($a['code'] ?? '') === 'existing_partner');
                $decisionInitialStatus = in_array(($decision['status'] ?? ''), ['approved', 'rejected', 'needs_info'], true)
                    ? $decision['status']
                    : 'approved';
                $emailUniquenessBlockers = collect($partnerMatches)
                    ->filter(fn ($m) => ($m['resolution'] ?? null) === 'keep_separate'
                        && ! empty($m['uniqueness']['email_shared_with_existing_login']))
                    ->values()
                    ->all();
                $hasEmailUniquenessBlocker = $emailUniquenessBlockers !== [];
            @endphp

            {{-- Define before Alpine evaluates nested x-data (must not break on parent tab scope). --}}
            <script>
                window.partnerMatchResolution = function partnerMatchResolution(config) {
                    config = config || {};
                    return {
                        matchOpen: false,
                        matchPhase: 'compare',
                        matchBusy: false,
                        matchIndex: 0,
                        matchAction: null,
                        confirmDespiteConflicts: false,
                        matches: Array.isArray(config.matches) ? config.matches : [],
                        resolveUrl: config.resolveUrl || '',
                        currentMatch() {
                            return this.matches[this.matchIndex] || null;
                        },
                        openMatch(index) {
                            this.matchIndex = typeof index === 'number' ? index : 0;
                            this.matchPhase = 'compare';
                            this.matchAction = null;
                            this.confirmDespiteConflicts = false;
                            this.matchBusy = false;
                            this.matchOpen = true;
                        },
                        startAction(action) {
                            this.matchAction = action;
                            this.matchPhase = 'review';
                            this.confirmDespiteConflicts = false;
                        },
                        backToCompare() {
                            this.matchPhase = 'compare';
                            this.matchAction = null;
                            this.matchBusy = false;
                        },
                        reviewTitle() {
                            if (this.matchAction === 'link') {
                                const m = this.currentMatch();
                                if (m?.is_affiliate_collision) return 'Use existing Affiliate?';
                                return 'Link identity to this Partner?';
                            }
                            if (this.matchAction === 'keep_separate') return 'Keep these records separate?';
                            return 'Confirm';
                        },
                        reviewMessage() {
                            const m = this.currentMatch();
                            if (!m) return '';
                            if (this.matchAction === 'link') {
                                let msg = m.same_person_helper || m.link_preview
                                    || 'This application will attach to that Partner identity on Approve.';
                                if ((m.conflict_fields || []).length) {
                                    msg += '\n\nConflicting fields: ' + (m.conflict_fields || []).join(', ') + '. Confirm only if investigation shows they are the same person.';
                                }
                                return msg;
                            }
                            if (this.matchAction === 'keep_separate') {
                                let msg = 'These records belong to different people. They will remain separate.\n\nThis collision will be marked reviewed for this application. Approve will not link to ' + (m.existing?.name || 'that Partner') + '.';
                                if (m.uniqueness?.email_shared_with_existing_login) {
                                    msg += '\n\nAny shared unique contact or identity information must still be resolved before approval.';
                                }
                                return msg;
                            }
                            return '';
                        },
                        confirmMatch() {
                            if (this.matchBusy || !this.matchAction) return;
                            const m = this.currentMatch();
                            if (!m) return;
                            if (this.matchAction === 'link' && (m.conflict_fields || []).length && !this.confirmDespiteConflicts) {
                                return;
                            }
                            this.matchBusy = true;
                            const form = this.$refs.matchResolveForm;
                            form.decision.value = this.matchAction;
                            form.partner_id.value = m.partner_id;
                            form.confirm_despite_conflicts.value = this.confirmDespiteConflicts ? '1' : '0';
                            form.submit();
                        },
                        pickRequestInfo() {
                            this.matchOpen = false;
                            this.$nextTick(() => {
                                const root = document.querySelector('[data-partner-decision-root]');
                                if (!root || !root._x_dataStack) return;
                                const d = root._x_dataStack[0];
                                if (!d) return;
                                d.status = 'needs_info';
                                d.phase = 'compose';
                                d.requestKind = 'information';
                                d.requestType = 'personal_information';
                                d.requestExplanation = 'Please provide clearer NIDA / TIN / supporting identity information so we can confirm whether this is a new Partner or an existing account.';
                            });
                        },
                    };
                };

                window.partnerApplicationDecision = function partnerApplicationDecision(config) {
                    config = config || {};
                    return {
                        phase: 'compose',
                        busy: false,
                        status: config.initialStatus || 'approved',
                        existingPartnerMatch: !!config.existingPartnerMatch,
                        unresolvedMatch: !!config.unresolvedMatch,
                        emailBlocked: !!config.emailBlocked,
                        emailPhase: 'idle',
                        emailDraft: '',
                        emailBusy: false,
                        requestKind: 'document',
                        requestMode: 'new',
                        requestType: '',
                        requestOtherMode: false,
                        requestOtherLabel: '',
                        requestExplanation: '',
                        replaceReason: '',
                        replaceReasonOtherMode: false,
                        replaceReasonOther: '',
                        rejectionReason: '',
                        adminNotes: '',
                        documentOptions: Object.entries(config.documentOptions || {}).map(([value, label]) => ({ value, label })),
                        informationOptions: Object.entries(config.informationOptions || {}).map(([value, label]) => ({ value, label })),
                        existingDocuments: Array.isArray(config.existingDocuments) ? config.existingDocuments : [],
                        replaceReasons: Object.entries(config.replaceReasons || {}).map(([value, label]) => ({ value, label })),
                        startChangeEmail() {
                            this.emailPhase = 'compose';
                            this.emailDraft = '';
                            this.emailBusy = false;
                        },
                        reviewChangeEmail() {
                            if (!(this.emailDraft || '').trim()) return;
                            this.emailPhase = 'review';
                        },
                        backToEmailCompose() {
                            this.emailPhase = 'compose';
                            this.emailBusy = false;
                        },
                        cancelChangeEmail() {
                            this.emailPhase = 'idle';
                            this.emailDraft = '';
                            this.emailBusy = false;
                        },
                        confirmChangeEmail() {
                            if (this.emailBusy) return;
                            const next = (this.emailDraft || '').trim();
                            if (! next) return;
                            this.emailBusy = true;
                            const form = document.querySelector('form[action*="change-applicant-email"]');
                            if (! form) {
                                this.emailBusy = false;
                                return;
                            }
                            const input = form.querySelector('input[name="email"]');
                            if (input) input.value = next;
                            form.submit();
                        },
                        currentOptions() {
                            if (this.requestKind === 'information') return this.informationOptions;
                            if (this.requestMode === 'replace') return this.existingDocuments;
                            const present = new Set(this.existingDocuments.map(d => d.value));
                            return this.documentOptions.filter(o => !present.has(o.value) || o.value === 'other_document');
                        },
                        pickRequestType(value) {
                            this.requestType = value;
                            this.requestOtherMode = (value === 'other_document' || value === 'other_information');
                            if (this.requestOtherMode) this.requestOtherLabel = '';
                        },
                        startReplace(value) {
                            this.requestKind = 'document';
                            this.requestMode = 'replace';
                            this.requestType = value;
                            this.requestOtherMode = false;
                            this.replaceReason = '';
                            this.replaceReasonOtherMode = false;
                            this.replaceReasonOther = '';
                        },
                        cancelOther() {
                            this.requestOtherMode = false;
                            this.requestType = '';
                            this.requestOtherLabel = '';
                        },
                        confirmOther() {
                            if (!(this.requestOtherLabel || '').trim()) return;
                            this.requestOtherMode = false;
                        },
                        pickReplaceReason(value) {
                            this.replaceReason = value;
                            this.replaceReasonOtherMode = value === 'other';
                            if (!this.replaceReasonOtherMode) this.replaceReasonOther = '';
                        },
                        cancelReplaceReasonOther() {
                            this.replaceReasonOtherMode = false;
                            this.replaceReason = '';
                            this.replaceReasonOther = '';
                        },
                        confirmReplaceReasonOther() {
                            if (!(this.replaceReasonOther || '').trim()) return;
                            this.replaceReasonOtherMode = false;
                        },
                        canReview() {
                            if (this.status === 'approved' && this.unresolvedMatch) return false;
                            if (this.status === 'approved' && this.emailBlocked) return false;
                            if (this.status === 'needs_info') {
                                if (!this.requestKind || !this.requestType) return false;
                                if ((this.requestType === 'other_document' || this.requestType === 'other_information')
                                    && !(this.requestOtherLabel || '').trim()) return false;
                                if (this.requestOtherMode) return false;
                                if (this.requestKind === 'document' && this.requestMode === 'replace') {
                                    if (!this.replaceReason) return false;
                                    if (this.replaceReason === 'other' && !(this.replaceReasonOther || '').trim()) return false;
                                    if (this.replaceReasonOtherMode) return false;
                                }
                                return true;
                            }
                            if (this.status === 'rejected') {
                                return !!this.rejectionReason && !!(this.adminNotes || '').trim();
                            }
                            return true;
                        },
                        primaryLabel() {
                            return this.status === 'approved' ? 'Review & approve'
                                : (this.status === 'rejected' ? 'Review & decline'
                                    : (this.status === 'needs_info' ? 'Review & request info' : 'Review'));
                        },
                        confirmTitle() {
                            return this.status === 'approved' ? 'Approve this application?'
                                : (this.status === 'rejected' ? 'Decline this application?'
                                    : (this.status === 'needs_info' ? 'Request this information?' : 'Save decision?'));
                        },
                        confirmMessage() {
                            if (this.status === 'approved') {
                                return this.existingPartnerMatch
                                    ? 'A possible existing Partner was reviewed. Approve follows that resolution — link only if staff chose Link; otherwise a new Partner identity is created when Keep separate and uniqueness allow it.'
                                    : 'This will create their ordinary Affiliate account using existing enrollment infrastructure. They must still accept the Affiliate Agreement before Share & Earn.';
                            }
                            if (this.status === 'needs_info') {
                                const label = this.requestOtherLabel || (this.currentOptions().find(o => o.value === this.requestType)?.label || this.requestType);
                                const reason = this.replaceReason === 'other'
                                    ? this.replaceReasonOther
                                    : (this.replaceReasons.find(r => r.value === this.replaceReason)?.label || '');
                                const modeNote = this.requestMode === 'replace' ? ('Update/replace — ' + reason + '\n') : '';
                                return modeNote + 'Applicant will see “' + label + '” on their tracking card.\n' + (this.requestExplanation || '');
                            }
                            if (this.status === 'rejected') {
                                return 'The applicant will see your description on tracking. No partner account will be created.';
                            }
                            return 'Keep this application under review.';
                        },
                        confirmLabel() {
                            return this.status === 'approved' ? 'Approve'
                                : (this.status === 'rejected' ? 'Decline'
                                    : (this.status === 'needs_info' ? 'Request information' : 'Save'));
                        },
                        onSubmit(event) {
                            if (this.busy) {
                                event.preventDefault();
                                return;
                            }
                            if (this.phase === 'compose') {
                                event.preventDefault();
                                if (!this.canReview()) return;
                                this.busy = true;
                                setTimeout(() => {
                                    this.busy = false;
                                    this.phase = 'review';
                                }, 180);
                            }
                        },
                        execute() {
                            if (this.busy) return;
                            this.busy = true;
                            // Native submit bypasses Alpine @submit — ensure Action select matches Alpine state.
                            const statusField = this.$refs.decisionForm.querySelector('[name="status"]');
                            if (statusField) statusField.value = this.status;
                            this.$refs.decisionForm.submit();
                        },
                    };
                };
            </script>

            <div class="rounded-2xl shadow-sm overflow-hidden ring-2 ring-brand/25 bg-gradient-to-b from-brand-muted/50 to-white lg:sticky lg:top-4"
                 data-partner-decision-root
                 x-data="partnerApplicationDecision(@js([
                    'initialStatus' => $decisionInitialStatus,
                    'existingPartnerMatch' => $existingPartnerMatch || count($partnerMatches) > 0,
                    'unresolvedMatch' => $hasUnresolvedPartnerMatch,
                    'emailBlocked' => $hasEmailUniquenessBlocker,
                    'documentOptions' => $requestCatalog['documents'] ?? [],
                    'informationOptions' => $requestCatalog['information'] ?? [],
                    'replaceReasons' => $requestCatalog['replace_reasons'] ?? [],
                    'existingDocuments' => $existingDocumentOptions,
                 ]))">
                <div class="bg-brand px-5 py-4 text-white">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-brand-gold">Review decision</h2>
                    <p class="text-sm text-white/80 mt-1">
                        @if (in_array($application->status, ['approved', 'rejected'], true))
                            Final decision
                        @else
                            Approve · Request information · Decline
                        @endif
                    </p>
                </div>

                @if (session('status'))
                    <div class="mx-5 mt-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mx-5 mt-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-3 py-2 text-sm text-red-700">
                        <ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                @if (in_array($application->status, ['approved', 'rejected'], true))
                    @php
                        $decisionPartner = $application->partner;
                        $identityLink = is_array($application->payload['identity_link'] ?? null)
                            ? $application->payload['identity_link']
                            : null;
                        $linkedIds = collect($application->payload['match_resolutions'] ?? [])
                            ->filter(fn ($row) => ($row['decision'] ?? '') === 'link')
                            ->keys();
                    @endphp
                    <div class="p-5 space-y-4">
                        <div class="rounded-xl bg-white ring-1 ring-brand/15 p-4 space-y-3 text-sm">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Decision</p>
                                <p class="mt-1 text-lg font-bold text-gray-900">
                                    {{ $application->status === 'approved' ? 'Approved' : 'Declined' }}
                                </p>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">
                                        {{ $application->status === 'approved' ? 'Approved by' : 'Declined by' }}
                                    </p>
                                    <p class="mt-0.5 font-semibold text-gray-900">{{ $decision['reviewer']?->name ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">
                                        {{ $application->status === 'approved' ? 'Approved on' : 'Declined on' }}
                                    </p>
                                    <p class="mt-0.5 font-semibold text-gray-900">
                                        {{ $decision['reviewed_at']?->format('d M Y H:i') ?? '—' }}
                                    </p>
                                </div>
                                @if ($application->status === 'approved' && $decisionPartner)
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Partner</p>
                                        <p class="mt-0.5 font-semibold text-gray-900">
                                            <a href="{{ route('admin.partners.show', ['vendor' => $decisionPartner->id, 'operational' => 1]) }}"
                                               class="text-brand hover:underline">
                                                {{ $decisionPartner->vendor_number ?: $decisionPartner->partner_number ?: 'P-'.$decisionPartner->id }}
                                            </a>
                                            <span class="text-gray-500 font-normal"> · {{ $decisionPartner->name }}</span>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Identity</p>
                                        <p class="mt-0.5 text-gray-800">
                                            @if ($identityLink || $linkedIds->isNotEmpty())
                                                Linked to existing Partner
                                            @else
                                                New Partner identity created
                                            @endif
                                        </p>
                                    </div>
                                @endif
                                @if ($application->status === 'rejected' && filled($application->admin_notes))
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Reason shown to applicant</p>
                                        <p class="mt-0.5 text-gray-800 whitespace-pre-line">{{ $application->admin_notes }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                <form method="POST" action="{{ route('admin.partner-applications.update', $application) }}" class="p-5 space-y-4"
                      x-ref="decisionForm"
                      @submit.prevent="onSubmit($event)">
                    @csrf @method('PUT')

                    <div x-show="phase === 'compose'" class="space-y-4">
                        @if ($hasUnresolvedPartnerMatch)
                            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-2.5 text-xs text-amber-950">
                                <p class="font-semibold text-sm">Possible existing Partner found</p>
                                <p class="mt-0.5 opacity-90">
                                    {{ count($unresolvedPartnerMatches) }} possible match{{ count($unresolvedPartnerMatches) === 1 ? '' : 'es' }}
                                    · {{ $unresolvedPartnerMatches[0]['match_summary'] ?? 'Review required' }}
                                </p>
                                <button type="button" @click="openMatch(0)"
                                        class="mt-2 inline-flex items-center font-bold text-brand hover:underline">
                                    Review match →
                                </button>
                            </div>
                        @elseif (count($partnerMatches) > 0)
                            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-3 py-2 text-xs text-sky-950">
                                <p class="font-semibold">Match reviewed</p>
                                <p class="mt-0.5 opacity-90">
                                    @php $rm = $partnerMatches[0]; @endphp
                                    {{ ($rm['resolution'] ?? '') === 'link'
                                        ? (($rm['is_affiliate_collision'] ?? false) ? 'Use existing Affiliate on Approve' : 'Link on Approve')
                                        : 'Keep separate' }}
                                    · {{ $rm['existing']['name'] ?? 'Partner' }}
                                    · {{ $rm['match_summary'] ?? '' }}
                                </p>
                                <button type="button" @click="openMatch(0)" class="mt-1.5 font-bold text-brand hover:underline">View comparison →</button>
                            </div>
                        @endif

                        @if ($hasEmailUniquenessBlocker)
                            @php $eb = $emailUniquenessBlockers[0]; @endphp
                            <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-3 text-xs text-rose-950 space-y-2"
                                 x-show="emailPhase === 'idle'" x-cloak>
                                <p class="font-semibold text-sm text-rose-950">Email needs attention</p>
                                <p class="opacity-90">
                                    These are separate people, but this email is already being used by another Partner:
                                </p>
                                <p class="font-mono text-sm font-semibold break-all">{{ $eb['uniqueness']['email'] ?? $application->email }}</p>
                                <p class="opacity-90">A unique email is required before this application can be approved.</p>
                                <div class="flex flex-col gap-2 pt-1">
                                    <button type="button" @click="startChangeEmail()"
                                            class="w-full text-left rounded-xl bg-white ring-1 ring-rose-200 hover:bg-rose-50/80 px-3 py-2.5">
                                        <span class="text-sm font-bold text-brand">Change applicant email</span>
                                    </button>
                                    <a href="{{ $eb['partner_url'] }}" target="_blank" rel="noopener"
                                       class="w-full text-left rounded-xl bg-white ring-1 ring-rose-200 hover:bg-rose-50/80 px-3 py-2.5">
                                        <span class="text-sm font-bold text-gray-900">Open {{ $eb['existing']['name'] ?? 'Partner' }} →</span>
                                    </a>
                                </div>
                            </div>

                            <div class="rounded-xl bg-white ring-1 ring-brand/20 p-3 space-y-3" x-show="emailPhase === 'compose'" x-cloak>
                                <p class="text-sm font-bold text-gray-900">Change applicant email</p>
                                <p class="text-xs text-gray-600">Current: <span class="font-mono">{{ $application->email }}</span></p>
                                <div>
                                    <label class="block text-xs font-semibold text-brand mb-1">New email <span class="text-red-500">*</span></label>
                                    <input type="email" x-model="emailDraft" autocomplete="off"
                                           class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm px-3 py-2.5"
                                           placeholder="unique@example.com">
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="cancelChangeEmail()" class="flex-1 rounded-xl ring-1 ring-gray-200 py-2.5 text-sm font-semibold text-gray-700">Cancel</button>
                                    <button type="button" @click="reviewChangeEmail()"
                                            class="flex-1 rounded-xl bg-brand text-white py-2.5 text-sm font-semibold disabled:opacity-40"
                                            :disabled="!(emailDraft || '').trim()">Review</button>
                                </div>
                            </div>

                            <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/15 p-3 space-y-3" x-show="emailPhase === 'review'" x-cloak>
                                <p class="text-sm font-bold text-brand">Confirm email change?</p>
                                <p class="text-xs text-brand/90">
                                    Applicant email will change from
                                    <span class="font-mono">{{ $application->email }}</span>
                                    to
                                    <span class="font-mono" x-text="emailDraft"></span>.
                                    The originally submitted email stays in application history. Duplicate detection will re-run.
                                </p>
                                <div class="flex gap-2">
                                    <button type="button" @click="backToEmailCompose()" class="flex-1 rounded-xl ring-1 ring-gray-200 py-2.5 text-sm font-semibold text-gray-700" :disabled="emailBusy">Back</button>
                                    <button type="button" @click="confirmChangeEmail()"
                                            class="flex-1 rounded-xl bg-brand text-white py-2.5 text-sm font-semibold disabled:opacity-50"
                                            :disabled="emailBusy"
                                            x-text="emailBusy ? 'Saving…' : 'Confirm'"></button>
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="block text-xs font-semibold text-brand mb-1">Action</label>
                            {{-- name=status on the visible control so Approve/Decline/Request submit exactly what the Owner selected --}}
                            <select name="status" x-model="status" class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand">
                                <option value="approved">Approve</option>
                                <option value="needs_info">Request information</option>
                                <option value="rejected">Decline</option>
                            </select>
                        </div>

                        <div x-show="status === 'needs_info'" x-cloak class="space-y-3 rounded-xl bg-white ring-1 ring-brand/15 p-3">
                            <div>
                                <p class="text-xs font-semibold text-brand mb-2">What do you need?</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="cursor-pointer">
                                        <input type="radio" class="peer sr-only" value="document" x-model="requestKind" @change="requestMode = 'new'; requestType = ''; replaceReason = ''" name="request_kind_ui">
                                        <span class="block rounded-xl ring-1 ring-gray-200 px-3 py-2 text-center text-xs font-semibold peer-checked:ring-brand peer-checked:bg-brand-muted/50">Document</span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" class="peer sr-only" value="information" x-model="requestKind" @change="requestMode = 'new'; requestType = ''; replaceReason = ''" name="request_kind_ui">
                                        <span class="block rounded-xl ring-1 ring-gray-200 px-3 py-2 text-center text-xs font-semibold peer-checked:ring-brand peer-checked:bg-brand-muted/50">Information</span>
                                    </label>
                                </div>
                            </div>

                            <div x-show="requestKind === 'document' && existingDocuments.length" x-cloak class="space-y-2">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Existing documents — request update</p>
                                <ul class="space-y-1 rounded-xl ring-1 ring-gray-200 p-1 max-h-40 overflow-y-auto">
                                    <template x-for="doc in existingDocuments" :key="'ex-'+doc.value">
                                        <li class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50">
                                            <span class="text-sm text-gray-800 truncate" x-text="doc.label"></span>
                                            <button type="button" @click="startReplace(doc.value)"
                                                    class="shrink-0 text-[11px] font-semibold text-brand px-2 py-1 rounded-lg ring-1 ring-brand/25 hover:bg-brand-muted/40"
                                                    :class="requestMode === 'replace' && requestType === doc.value ? 'bg-brand-muted text-brand' : ''">
                                                Request update
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <div x-show="requestKind && !requestOtherMode && !(requestMode === 'replace' && requestType)" x-cloak>
                                <label class="block text-xs font-semibold text-brand mb-1" x-text="requestKind === 'document' ? 'Request new document' : 'Select'"></label>
                                <div class="max-h-48 overflow-y-auto space-y-1 rounded-xl ring-1 ring-gray-200 p-1">
                                    <template x-for="opt in currentOptions()" :key="opt.value">
                                        <button type="button" @click="requestMode = 'new'; pickRequestType(opt.value)"
                                                class="w-full text-left px-3 py-2 rounded-lg text-sm"
                                                :class="requestMode === 'new' && requestType === opt.value ? 'bg-brand-muted text-brand font-semibold' : 'hover:bg-gray-50 text-gray-800'"
                                                x-text="opt.label"></button>
                                    </template>
                                </div>
                            </div>

                            <div x-show="requestMode === 'replace' && requestType && !replaceReasonOtherMode" x-cloak class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-brand truncate">
                                        Update: <span x-text="existingDocuments.find(d => d.value === requestType)?.label || requestType"></span>
                                    </p>
                                    <button type="button" @click="requestMode = 'new'; requestType = ''; replaceReason = ''" class="text-xs font-semibold text-gray-500">Cancel</button>
                                </div>
                                <label class="block text-xs font-semibold text-brand mb-1">Reason <span class="text-red-500">*</span></label>
                                <div class="max-h-40 overflow-y-auto space-y-1 rounded-xl ring-1 ring-gray-200 p-1">
                                    <template x-for="reason in replaceReasons" :key="reason.value">
                                        <button type="button" @click="pickReplaceReason(reason.value)"
                                                class="w-full text-left px-3 py-2 rounded-lg text-sm"
                                                :class="replaceReason === reason.value ? 'bg-brand-muted text-brand font-semibold' : 'hover:bg-gray-50 text-gray-800'"
                                                x-text="reason.label"></button>
                                    </template>
                                </div>
                            </div>

                            <div x-show="replaceReasonOtherMode" x-cloak class="space-y-2">
                                <label class="block text-xs font-semibold text-brand">Custom reason <span class="text-red-500">*</span></label>
                                <input type="text" x-model="replaceReasonOther" maxlength="200"
                                       class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm px-3 py-2.5"
                                       placeholder="Describe why the document must be updated">
                                <div class="flex justify-between gap-2">
                                    <button type="button" @click="cancelReplaceReasonOther()" class="text-sm font-semibold text-gray-600 px-2 py-2">Cancel</button>
                                    <button type="button" @click="confirmReplaceReasonOther()" class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2"
                                            :disabled="!(replaceReasonOther || '').trim()"
                                            :class="!(replaceReasonOther || '').trim() ? 'opacity-40 pointer-events-none' : ''">Continue</button>
                                </div>
                            </div>

                            <div x-show="requestOtherMode" x-cloak class="space-y-2">
                                <label class="block text-xs font-semibold text-brand">Custom request <span class="text-red-500">*</span></label>
                                <input type="text" x-model="requestOtherLabel" maxlength="120"
                                       class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm px-3 py-2.5"
                                       placeholder="Describe what is required">
                                <div class="flex justify-between gap-2">
                                    <button type="button" @click="cancelOther()" class="text-sm font-semibold text-gray-600 px-2 py-2">Cancel</button>
                                    <button type="button" @click="confirmOther()" class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2"
                                            :disabled="!(requestOtherLabel || '').trim()"
                                            :class="!(requestOtherLabel || '').trim() ? 'opacity-40 pointer-events-none' : ''">Continue</button>
                                </div>
                            </div>

                            <div x-show="requestType && !requestOtherMode && !replaceReasonOtherMode && (requestMode !== 'replace' || replaceReason)" x-cloak>
                                <label class="block text-xs font-semibold text-brand mb-1">Short instruction (optional)</label>
                                <textarea x-model="requestExplanation" rows="2"
                                          class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm"
                                          placeholder="Shown to the applicant with this request"></textarea>
                            </div>
                        </div>

                        <div x-show="status === 'rejected'" x-cloak class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-brand mb-1">Decline reason <span class="text-red-500">*</span></label>
                                <select name="rejection_reason" x-model="rejectionReason"
                                        :disabled="status !== 'rejected'"
                                        class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm">
                                    <option value="">— Select reason —</option>
                                    @foreach ($review['rejection_reason_codes'] as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-brand mb-1">Description for applicant <span class="text-red-500">*</span></label>
                                <textarea name="admin_notes" x-model="adminNotes" rows="3"
                                          :disabled="status !== 'rejected'"
                                          class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm"
                                          placeholder="Clear reason the applicant will see on tracking"></textarea>
                            </div>
                        </div>

                        <div x-show="status === 'approved'" x-cloak>
                            <label class="block text-xs font-semibold text-brand mb-1">Internal notes (optional)</label>
                            <textarea name="admin_notes" x-model="adminNotes" rows="2"
                                      :disabled="status !== 'approved'"
                                      class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm"
                                      placeholder="Optional internal notes"></textarea>
                            <p x-show="unresolvedMatch" x-cloak class="mt-2 text-xs text-amber-900 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
                                Resolve the possible Partner match before Approve.
                            </p>
                            <p x-show="!unresolvedMatch && emailBlocked" x-cloak class="mt-2 text-xs text-rose-900 bg-rose-50 ring-1 ring-rose-200 rounded-lg px-3 py-2">
                                Correct the shared email before Approve — Keep separate does not bypass uniqueness.
                            </p>
                            <p x-show="!unresolvedMatch && !emailBlocked && existingPartnerMatch" x-cloak class="mt-2 text-xs text-sky-900 bg-sky-50 ring-1 ring-sky-200 rounded-lg px-3 py-2">
                                Approve will follow the match resolution recorded above.
                            </p>
                        </div>

                        <div x-show="status === 'needs_info'" x-cloak>
                            {{-- Only include request fields when Action is Request information; never on Approve/Decline. --}}
                            <template x-if="status === 'needs_info'">
                                <div>
                                    <input type="hidden" name="request_kind" :value="requestKind">
                                    <input type="hidden" name="request_type" :value="requestType">
                                    <input type="hidden" name="request_mode" :value="requestMode">
                                    <input type="hidden" name="request_other_label" :value="requestOtherLabel">
                                    <input type="hidden" name="request_explanation" :value="requestExplanation">
                                    <input type="hidden" name="replace_reason" :value="replaceReason">
                                    <input type="hidden" name="replace_reason_other" :value="replaceReasonOther">
                                </div>
                            </template>
                        </div>

                        <button type="submit"
                                class="w-full bg-brand hover:bg-brand-light text-white font-semibold rounded-xl px-4 py-3 text-sm shadow-sm disabled:opacity-50"
                                :disabled="busy || !canReview()"
                                x-text="busy ? 'Reviewing…' : primaryLabel()">
                            Review
                        </button>
                    </div>

                    <div x-show="phase === 'review'" x-cloak class="space-y-4">
                        <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/15 p-4 text-sm text-brand space-y-2">
                            <p class="font-bold" x-text="confirmTitle()"></p>
                            <p class="text-brand/90 whitespace-pre-line" x-text="confirmMessage()"></p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="phase = 'compose'; busy = false"
                                    class="flex-1 rounded-xl ring-1 ring-gray-200 py-3 text-sm font-semibold text-gray-700"
                                    :disabled="busy">Back</button>
                            <button type="button" @click="execute()"
                                    class="flex-1 rounded-xl bg-brand hover:bg-brand-light text-white py-3 text-sm font-semibold disabled:opacity-50"
                                    :disabled="busy"
                                    x-text="busy ? 'Saving…' : confirmLabel()">Confirm</button>
                        </div>
                    </div>
                </form>
                @endif
            </div>

            @include('admin.partner-applications._match-resolution')

            @if (! in_array($application->status, ['approved', 'rejected'], true) && ($decision['reviewer'] || $decision['reviewed_at']))
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
