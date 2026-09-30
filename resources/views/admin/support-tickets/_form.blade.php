{{-- Support ticket form — Member|Guest intake, searchable member, Category→Subject. --}}
@php
    $r = $record ?? null;
    $customers = $customers ?? collect();
    $agents = $agents ?? collect();
    $categories = $categories ?? [];
    $subjectsByCategory = $subjectsByCategory ?? [];
    $priorities = $priorities ?? [];
    $statuses = $statuses ?? [];
    $sources = $sources ?? [];
    $contactKinds = $contactKinds ?? ['customer' => 'Member', 'guest' => 'Guest'];
    $agentCount = (int) ($agentCount ?? 0);
    $canOverrideAssignment = (bool) ($canOverrideAssignment ?? false);
    $ticketNumberPreview = $ticketNumberPreview ?? 'SUP-….';
    $similarSearchUrl = $similarSearchUrl ?? route('admin.support-tickets.similar');
    $defaultPriorities = $defaultPriorities ?? [];
    $storedCategory = old('category', $r?->category ?? '');
    $categoryKey = old('category', $storedCategory !== '' ? \App\Support\SupportTaxonomy::categoryKeyForStored($storedCategory) : '');
    $categoryOther = old('category_other', ($categoryKey === 'other' && $storedCategory && $storedCategory !== 'other') ? $storedCategory : '');
    $initialKind = old('contact_kind', $r?->contact_kind ?? 'customer');
    if ($initialKind === 'member') {
        $initialKind = 'customer';
    }
    $initialCategory = $categoryKey;
    $initialSubject = old('subject', $r?->subject ?? '');
    $subjectOptions = $initialCategory ? \App\Support\SupportTaxonomy::subjectsFor($initialCategory) : [];
    $subjectIsOther = $initialSubject !== '' && ! in_array($initialSubject, $subjectOptions, true);
    if ($subjectIsOther) {
        $initialSubjectSelect = 'Other';
        $subjectOther = old('subject_other', $initialSubject);
    } else {
        $initialSubjectSelect = $initialSubject;
        $subjectOther = old('subject_other', '');
    }
    $customerSearchUrl = route('admin.support-tickets.customers');
    $selectedCustomerId = old('customer_id', $r?->customer_id);
    $selectedCustomerLabel = '';
    if ($selectedCustomerId && $r?->customer) {
        $c = $r->customer;
        $parts = array_filter([
            trim($c->first_name.' '.$c->last_name) ?: null,
            $c->phone ?: null,
            $c->member_no ?: ($c->customer_number ?: null),
            operator_email_display($c->email) !== '—' ? operator_email_display($c->email) : null,
        ]);
        $selectedCustomerLabel = $parts !== [] ? implode(' · ', $parts) : 'Member #'.$c->id;
    } elseif ($selectedCustomerId && isset($customers[$selectedCustomerId])) {
        $selectedCustomerLabel = $customers[$selectedCustomerId];
    }
    $isCreate = ! $r;
    $createAnyway = (bool) old('create_anyway', false);
@endphp

<div class="space-y-6" x-data="supportTicketForm({
    kind: @js($initialKind),
    category: @js($initialCategory),
    subject: @js($initialSubjectSelect),
    subjectsByCategory: @js($subjectsByCategory),
    defaultPriorities: @js($defaultPriorities),
    customerSearchUrl: @js($customerSearchUrl),
    similarSearchUrl: @js($similarSearchUrl),
    customerId: @js($selectedCustomerId ? (string) $selectedCustomerId : ''),
    customerLabel: @js($selectedCustomerLabel),
    excludeId: @js($r?->id),
    createAnyway: @js($createAnyway),
    checkSimilarOnCreate: @js($isCreate),
})">

    <x-admin.step title="Contact">
        <div class="md:col-span-2">
            <p class="block text-xs font-semibold text-gray-700 mb-2">Who is contacting us?</p>
            <div class="inline-flex rounded-xl ring-1 ring-brand/15 bg-white p-1 gap-1">
                <button type="button"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition"
                        :class="kind === 'customer' ? 'bg-brand text-white' : 'text-gray-700 hover:bg-brand-muted/40'"
                        @click="setKind('customer')">Member</button>
                <button type="button"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition"
                        :class="kind === 'guest' ? 'bg-brand text-white' : 'text-gray-700 hover:bg-brand-muted/40'"
                        @click="setKind('guest')">Guest</button>
            </div>
            <input type="hidden" name="contact_kind" :value="kind">
            <p class="mt-2 text-xs text-gray-500">Guest does not create a Member record. You can link to a member later.</p>
        </div>

        <div class="md:col-span-2" x-show="kind === 'customer'" x-cloak @error('customer_id') data-has-error="true" @enderror>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Member <span class="text-red-500">*</span></label>
            <input type="hidden" name="customer_id" :value="customerId" @error('customer_id') aria-invalid="true" @enderror>
            <div class="relative">
                <input type="search"
                       x-model="customerQuery"
                       @input.debounce.300ms="searchCustomers()"
                       @focus="if (customerQuery.length >= 1) searchCustomers()"
                       placeholder="Search name, phone, member number, or email"
                       @class([
                           'w-full text-sm bg-white border rounded-xl shadow-sm px-3.5 py-2.5 font-medium text-gray-700 focus:outline-none focus:ring-2',
                           'border-red-400 focus:border-red-500 focus:ring-red-200' => $errors->has('customer_id'),
                           'border-brand/15 focus:border-brand focus:ring-brand/15' => ! $errors->has('customer_id'),
                       ])
                       autocomplete="off">
                <p class="mt-1 text-xs text-gray-500" x-show="customerLabel" x-text="'Selected: ' + customerLabel"></p>
                <div x-show="customerResults.length" x-cloak
                     class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto rounded-xl border border-brand/15 bg-white shadow-lg">
                    <template x-for="row in customerResults" :key="row.id">
                        <button type="button"
                                class="w-full text-left px-3.5 py-2.5 text-sm hover:bg-brand-muted/50 border-b border-gray-50 last:border-0"
                                @click="pickCustomer(row)"
                                x-text="row.label"></button>
                    </template>
                </div>
            </div>
            @error('customer_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-amber-700" x-show="customerSearched && customerResults.length === 0 && customerQuery.length >= 2">
                No matching members. Switch to Guest if they are not registered yet.
            </p>
        </div>

        <template x-if="kind === 'guest'">
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-admin.input name="guest_name" label="Full name" :value="old('guest_name', $r?->guest_name)" required />
                <div>
                    <x-site.phone-input
                        name="guest_phone"
                        :label="'Country / phone'"
                        :value="old('guest_phone', $r?->guest_phone)"
                        :required="true"
                        :allow-country-change="true"
                        variant="rounded"
                        :help="null"
                    />
                </div>
                <x-admin.input name="guest_email" label="Email (optional)" :value="old('guest_email', $r?->guest_email)" type="email" />
            </div>
        </template>

        <x-admin.select name="source" label="Source" :options="$sources" :value="$r?->source ?? 'admin'" />
    </x-admin.step>

    <x-admin.step title="Issue">
        <div @error('category') data-has-error="true" @enderror>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
            <select name="category" x-model="category" @change="onCategoryChange()" required
                    @error('category') aria-invalid="true" @enderror
                    @class([
                        'appearance-none w-full text-sm bg-white border rounded-xl shadow-sm pl-3.5 pr-9 py-2.5 font-medium text-gray-700',
                        'border-red-400' => $errors->has('category'),
                        'border-brand/15' => ! $errors->has('category'),
                    ])>
                <option value="">— Select category —</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('category')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div @error('subject') data-has-error="true" @enderror>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
            <select name="subject" x-model="subject" @change="onSubjectChange()" required
                    @error('subject') aria-invalid="true" @enderror
                    @class([
                        'appearance-none w-full text-sm bg-white border rounded-xl shadow-sm pl-3.5 pr-9 py-2.5 font-medium text-gray-700',
                        'border-red-400' => $errors->has('subject'),
                        'border-brand/15' => ! $errors->has('subject'),
                    ])
                    :disabled="!category">
                <option value="">— Select subject —</option>
                <template x-for="item in subjectOptions" :key="item">
                    <option :value="item" x-text="item" :selected="subject === item"></option>
                </template>
            </select>
            @error('subject')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="md:col-span-2" x-show="category === 'other'" x-cloak>
            <x-admin.input name="category_other" label="Custom category" :value="$categoryOther" placeholder="Describe the category" />
        </div>
        <div class="md:col-span-2" x-show="subject === 'Other'" x-cloak>
            <x-admin.input name="subject_other" label="Custom subject" :value="$subjectOther" placeholder="Describe the subject" />
        </div>
        <div class="md:col-span-2">
            <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="4" />
        </div>

        @if ($isCreate)
            <div class="md:col-span-2" x-show="similarTickets.length > 0" x-cloak>
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">Similar tickets found</p>
                    <ul class="mt-2 space-y-1.5">
                        <template x-for="row in similarTickets" :key="row.ticket_number">
                            <li class="text-xs">
                                <span class="font-mono font-semibold" x-text="row.ticket_number"></span>
                                <span class="text-amber-800"> · </span>
                                <span x-text="row.subject"></span>
                                <span class="text-amber-700" x-text="' · ' + row.status"></span>
                                <span class="block text-amber-700/80" x-show="row.resolution_summary" x-text="row.resolution_summary"></span>
                            </li>
                        </template>
                    </ul>
                    <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold cursor-pointer">
                        <input type="checkbox" name="create_anyway" value="1" x-model="createAnyway"
                               class="rounded border-amber-300 text-brand focus:ring-brand/30">
                        Create anyway
                    </label>
                </div>
            </div>
        @endif
    </x-admin.step>

    <x-admin.step title="Triage / Review">
        <div class="md:col-span-2 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700">
            <p class="font-semibold text-brand">Ticket number</p>
            <p class="mt-1 font-mono text-gray-900">{{ $ticketNumberPreview }}</p>
            <p class="mt-1 text-xs text-gray-500">
                @if ($r)
                    Assigned at creation — not editable here.
                @else
                    Auto-generated on create (Settings prefix · year · sequence).
                @endif
            </p>
        </div>

        @if ($agentCount === 0)
            <div class="md:col-span-2 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">No Support Agents yet</p>
                <p class="mt-1">Create a staff user under Settings → Company → Users with capability <strong>Customer Support</strong>. New tickets will then auto-assign by round-robin. Until then, tickets stay Unassigned.</p>
                <a href="{{ route('admin.users.create') }}" class="inline-flex mt-2 font-semibold text-brand hover:underline">Create Support Agent →</a>
            </div>
        @endif

        @if ($canOverrideAssignment)
            <x-admin.select
                name="assigned_to"
                label="Assigned to"
                :options="$agents"
                :value="$r?->assigned_to"
                :placeholder="$agentCount === 0 ? '— No agents available —' : '— Auto-assign (round-robin) —'"
            />
            <p class="md:col-span-2 -mt-3 text-xs text-gray-500">Leave blank for automatic balanced assignment. Supervisors may override.</p>
        @else
            <div class="md:col-span-2">
                <p class="block text-xs font-semibold text-gray-700 mb-1">Assigned to</p>
                <p class="text-sm text-gray-800">
                    {{ $r?->assignee?->name ?? 'Auto-assign (round-robin)' }}
                </p>
                <input type="hidden" name="assigned_to" value="">
            </div>
        @endif

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Priority <span class="text-red-500">*</span></label>
            <select name="priority" x-ref="priority" required
                    class="appearance-none w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm pl-3.5 pr-9 py-2.5 font-medium text-gray-700">
                @foreach ($priorities as $key => $label)
                    <option value="{{ $key }}" @selected(old('priority', $r?->priority ?? 'normal') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <x-admin.select name="status" label="Status" :options="$statuses" :value="$r?->status ?? 'open'" required />
        @if ($r)
            <x-admin.input name="resolved_at" label="Resolved at" :value="optional($r?->resolved_at)->format('Y-m-d')" type="date" />
            <div class="md:col-span-2">
                <x-admin.textarea name="resolution_notes" label="Resolution notes" :value="$r?->resolution_notes" rows="2" />
            </div>
        @endif
    </x-admin.step>
</div>

@once
    <script>
        function supportTicketForm(cfg) {
            return {
                kind: cfg.kind || 'customer',
                category: cfg.category || '',
                subject: cfg.subject || '',
                subjectsByCategory: cfg.subjectsByCategory || {},
                defaultPriorities: cfg.defaultPriorities || {},
                customerSearchUrl: cfg.customerSearchUrl,
                similarSearchUrl: cfg.similarSearchUrl,
                customerId: cfg.customerId || '',
                customerLabel: cfg.customerLabel || '',
                customerQuery: cfg.customerLabel || '',
                customerResults: [],
                customerSearched: false,
                similarTickets: [],
                createAnyway: !!cfg.createAnyway,
                checkSimilarOnCreate: !!cfg.checkSimilarOnCreate,
                excludeId: cfg.excludeId || null,
                get subjectOptions() {
                    return this.subjectsByCategory[this.category] || [];
                },
                init() {
                    this.$watch('subject', () => this.lookupSimilar());
                    this.$watch('category', () => this.applyDefaultPriority());
                    if (this.checkSimilarOnCreate && this.category && this.subject) {
                        this.lookupSimilar();
                    }
                    const form = this.$el.closest('form');
                    if (form && this.checkSimilarOnCreate) {
                        form.addEventListener('submit', (e) => {
                            if (this.similarTickets.length > 0 && ! this.createAnyway) {
                                e.preventDefault();
                                this.$el.querySelector('[name="create_anyway"][type="checkbox"]')?.focus();
                            }
                        });
                    }
                },
                setKind(kind) {
                    this.kind = kind;
                    if (kind === 'guest') {
                        this.customerId = '';
                        this.customerLabel = '';
                        this.customerQuery = '';
                        this.customerResults = [];
                    }
                },
                onCategoryChange() {
                    const opts = this.subjectOptions;
                    if (! opts.includes(this.subject)) {
                        this.subject = '';
                    }
                    this.createAnyway = false;
                    this.applyDefaultPriority();
                    this.lookupSimilar();
                },
                onSubjectChange() {
                    this.createAnyway = false;
                    this.lookupSimilar();
                },
                applyDefaultPriority() {
                    if (! this.checkSimilarOnCreate) {
                        return;
                    }
                    const priority = this.defaultPriorities[this.category];
                    const el = this.$refs.priority;
                    if (priority && el && ! el.dataset.userTouched) {
                        el.value = priority;
                    }
                },
                async lookupSimilar() {
                    if (! this.checkSimilarOnCreate || ! this.category || ! this.subject) {
                        this.similarTickets = [];
                        return;
                    }
                    try {
                        const params = new URLSearchParams({
                            category: this.category,
                            subject: this.subject,
                        });
                        if (this.excludeId) {
                            params.set('exclude_id', String(this.excludeId));
                        }
                        const res = await fetch(this.similarSearchUrl + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const json = await res.json();
                        this.similarTickets = json.data || [];
                    } catch (e) {
                        this.similarTickets = [];
                    }
                },
                async searchCustomers() {
                    const q = (this.customerQuery || '').trim();
                    if (q.length < 2) {
                        this.customerResults = [];
                        this.customerSearched = false;
                        return;
                    }
                    this.customerSearched = true;
                    try {
                        const res = await fetch(this.customerSearchUrl + '?q=' + encodeURIComponent(q), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const json = await res.json();
                        this.customerResults = json.data || [];
                    } catch (e) {
                        this.customerResults = [];
                    }
                },
                pickCustomer(row) {
                    this.customerId = String(row.id);
                    this.customerLabel = row.label;
                    this.customerQuery = row.label;
                    this.customerResults = [];
                },
            };
        }
    </script>
@endonce
