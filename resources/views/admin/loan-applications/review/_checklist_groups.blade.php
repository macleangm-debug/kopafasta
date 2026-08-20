{{-- Shared Pass/Fail group accordion. Expects: $groups, $canEdit, and Alpine openGroup/openItem/passRemaining in parent. --}}
@foreach ($groups as $group)
    @php
        $groupKey = (string) ($group['key'] ?? '');
    @endphp
    <div class="rounded-2xl ring-2 ring-brand/15 overflow-hidden shadow-sm bg-white">
        <div class="px-4 py-3.5 bg-brand text-white flex flex-wrap items-center justify-between gap-2">
            <button type="button" class="text-left min-w-0 flex-1" @click="toggleGroup(@js($groupKey))">
                <h4 class="text-base font-extrabold tracking-tight inline-flex items-center gap-2">
                    <svg class="size-4 text-brand-gold transition" :class="openGroup === @js($groupKey) ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    <span>{{ is_scalar($group['label'] ?? null) ? $group['label'] : ($groupKey ?: 'Checks') }}</span>
                </h4>
                <p class="text-[11px] text-white/80 mt-0.5 tabular-nums">
                    {{ $group['decided'] ?? 0 }}/{{ $group['total'] ?? count($group['items'] ?? []) }} reviewed
                    @if (($group['failed'] ?? 0) > 0)
                        · <span class="text-rose-200 font-semibold">{{ $group['failed'] }} fail</span>
                    @elseif ($group['complete'] ?? false)
                        · <span class="text-brand-gold font-semibold">Done</span>
                    @endif
                </p>
            </button>
            @if ($canEdit && ! ($group['complete'] ?? false))
                <button type="button"
                        @click.stop="passRemaining(@js($groupKey))"
                        class="shrink-0 text-[11px] font-bold text-brand bg-brand-gold hover:brightness-95 px-2.5 py-1.5 rounded-lg">
                    Pass remaining
                </button>
            @endif
        </div>
        @if ($groupKey === 'collateral')
            @php
                $coverageLoan = $record ?? request()->route('loan_application');
                $csGap = $coverageLoan instanceof \App\Models\LoanApplication
                    ? app(\App\Services\CollateralSecureService::class)->viewModel($coverageLoan)
                    : [];
            @endphp
            @if (! empty($csGap['no_regional_cover']) && ! empty($csGap['valuer_unassigned']))
                <div x-show="openGroup === @js($groupKey)" x-cloak class="px-4 py-3 bg-amber-50 border-b border-amber-100 space-y-2">
                    <p class="text-sm font-semibold text-amber-950">
                        Fee is paid. No valuer covers {{ $coverageLoan->customer?->region ?: 'this region' }}.
                    </p>
                    @include('admin.loan-applications.review._request_partner_coverage', [
                        'coverageApplication' => $coverageLoan,
                        'coverageCategory' => 'valuer',
                        'coverageRegion' => $coverageLoan->customer?->region,
                    ])
                </div>
            @endif
        @endif
        <ul x-show="openGroup === @js($groupKey)" x-cloak x-ref="items_{{ $groupKey }}" class="divide-y divide-gray-50 bg-white">
            @foreach ($group['items'] as $item)
                @php
                    [$ig, $ik] = array_pad(explode('.', $item['key'], 2), 2, '');
                    $fieldBase = "items[{$ig}][{$ik}]";
                    $itemKey = (string) ($item['key'] ?? '');
                    $compareRows = $item['evidence']['compare'] ?? [];
                    $mismatchCount = collect($compareRows)->where('status', 'mismatch')->count();
                @endphp
                <li class="p-4" data-checklist-item
                    x-data="{
                        verdict: @js($item['verdict'] ?? ''),
                        reason: @js($item['fail_reason_code'] ?? ''),
                        needsStatementTotals: {{ ! empty($item['captures_statement']) ? 'true' : 'false' }},
                        systemLocked: {{ ! empty($item['read_only']) ? 'true' : 'false' }}
                    }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <button type="button" class="text-left min-w-0 flex-1" @click="toggleItem(@js($itemKey))">
                                            <p class="text-sm font-semibold text-gray-900 inline-flex items-center gap-2">
                                                <span>{{ $item['label'] }}</span>
                                                @if (($item['risk'] ?? 'normal') === 'critical')
                                                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-md px-1.5 py-0.5 bg-rose-100 text-rose-900 ring-1 ring-rose-200">High risk</span>
                                                @elseif (($item['risk'] ?? '') === 'elevated')
                                                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-md px-1.5 py-0.5 bg-amber-100 text-amber-900 ring-1 ring-amber-200">Elevated</span>
                                                @endif
                                                @if ($item['system_checked'] ?? false)
                                                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-md px-1.5 py-0.5 bg-sky-100 text-sky-900 ring-1 ring-sky-200">System</span>
                                                @endif
                                                @if ($item['documents_checked'] ?? false)
                                                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-md px-1.5 py-0.5 bg-violet-100 text-violet-900 ring-1 ring-violet-200">Documents</span>
                                                @elseif (! empty($item['document_link']))
                                                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-md px-1.5 py-0.5 bg-violet-50 text-violet-800 ring-1 ring-violet-100">Docs linked</span>
                                                @endif
                                                <svg class="size-3.5 text-gray-400 transition" :class="openItem === @js($itemKey) ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                            </p>
                            @if ($item['evidence']['hint'] ?? null)
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $item['evidence']['hint'] }}</p>
                            @endif
                            @if (! empty($item['document_link']['label']))
                                <p class="text-[11px] mt-0.5 {{ ($item['document_link']['status'] ?? '') === 'rejected' ? 'text-rose-700 font-semibold' : (($item['document_link']['status'] ?? '') === 'verified' ? 'text-violet-800 font-medium' : 'text-violet-700') }}">
                                    {{ $item['document_link']['label'] }}
                                    <a href="{{ route('admin.loan-applications.show', array_filter([
                                            'loan_application' => $record ?? request()->route('loan_application'),
                                            'workspace' => 'checklist',
                                            'capacity_tab' => 'documents',
                                            'review_person' => request('review_person'),
                                            'review_g' => request('review_g'),
                                            'review_m' => request('review_m'),
                                        ])) }}#review-documents"
                                       class="underline underline-offset-2 hover:text-brand">Open Documents</a>
                                </p>
                            @endif
                            @if ($mismatchCount > 0)
                                <p class="text-[11px] font-semibold text-amber-700 mt-0.5">{{ $mismatchCount }} difference{{ $mismatchCount === 1 ? '' : 's' }} vs CRB — expand to compare</p>
                            @endif
                        </button>
                        <div class="flex flex-wrap gap-1.5 shrink-0">
                            @if (! empty($item['awaiting_data']))
                                <span class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold bg-amber-50 text-amber-900 ring-1 ring-amber-200">Awaiting data</span>
                            @elseif (! empty($item['read_only']) || ! empty($item['captures_statement']) || ! empty($item['catalog_system']))
                                @if (($item['verdict'] ?? '') === 'pass')
                                    <span class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200">Pass ✓</span>
                                @elseif (($item['verdict'] ?? '') === 'fail')
                                    <span class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold bg-rose-50 text-rose-900 ring-1 ring-rose-200">Fail ✗</span>
                                @elseif (($item['verdict'] ?? '') === 'na')
                                    <span class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold bg-sky-100 text-sky-900 ring-1 ring-sky-300">N/A</span>
                                @endif
                            @else
                                <label class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1 cursor-pointer"
                                       :class="verdict === 'pass' ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-white text-gray-600 ring-gray-200'">
                                    <input type="radio" class="sr-only" name="{{ $fieldBase }}[verdict]" value="pass"
                                           x-model="verdict" @change="openItem = @js($itemKey)">
                                    Pass ✓
                                </label>
                                <label class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1 cursor-pointer"
                                       :class="verdict === 'fail' ? 'bg-rose-50 text-rose-900 ring-rose-200' : 'bg-white text-gray-600 ring-gray-200'">
                                    <input type="radio" class="sr-only" name="{{ $fieldBase }}[verdict]" value="fail"
                                           x-model="verdict" @change="openItem = @js($itemKey)">
                                    Fail ✗
                                </label>
                                <label class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1 cursor-pointer"
                                       :class="verdict === 'na' ? 'bg-sky-100 text-sky-900 ring-sky-300 shadow-sm' : 'bg-white text-gray-600 ring-gray-200'">
                                    <input type="radio" class="sr-only" name="{{ $fieldBase }}[verdict]" value="na"
                                           x-model="verdict" @change="openItem = @js($itemKey)">
                                    N/A
                                </label>
                            @endif
                        </div>
                    </div>

                    <div x-show="openItem === @js($itemKey)" x-cloak class="mt-3 space-y-3">
                        @if (! empty($item['awaiting_data']))
                            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3.5 py-3">
                                <p class="text-sm font-semibold text-amber-950">{{ $item['awaiting_message'] ?? 'There is no data for this checklist' }}</p>
                                @if (! empty($item['awaiting_cta']['href']))
                                    <a href="{{ $item['awaiting_cta']['href'] }}"
                                       class="inline-flex mt-2 rounded-lg bg-white text-amber-950 text-[11px] font-bold px-2.5 py-1.5 ring-1 ring-amber-200 hover:bg-amber-100">
                                        {{ $item['awaiting_cta']['label'] ?? 'Open' }}
                                    </a>
                                @endif
                            </div>
                        @endif
                        @if (! empty($item['evidence']['documents']) || ($item['evidence']['layout'] ?? null) === 'documents')
                            <div class="rounded-xl ring-1 ring-brand/15 overflow-hidden bg-white">
                                <div class="px-3.5 py-2.5 bg-gradient-to-r from-brand-muted/60 to-white border-b border-brand/10">
                                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ $item['evidence']['documents_heading'] ?? 'Documents on file' }}</p>
                                    <p class="text-[11px] text-gray-600 mt-0.5">
                                        {{ ($item['evidence_type'] ?? '') === 'activity'
                                            ? 'Open activity proof documents — contracts, licences, business photos, TIN, etc.'
                                            : 'Open full bank / mobile money statement — PDF and images supported.' }}
                                    </p>
                                </div>
                                @if (! empty($item['evidence']['documents']))
                                    <div class="p-3.5 grid sm:grid-cols-2 gap-3">
                                        @foreach ($item['evidence']['documents'] as $doc)
                                            <div class="rounded-xl ring-1 ring-brand/10 bg-brand-muted/20 p-3 flex gap-3 items-start">
                                                <x-admin.document-preview
                                                    :url="$doc['url']"
                                                    :label="$doc['label'] ?? 'Document'"
                                                    variant="thumbnail" />
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-semibold text-gray-900">{{ $doc['label'] ?? 'Document' }}</p>
                                                    <p class="text-[11px] text-gray-500 mt-0.5 capitalize">
                                                        {{ ($doc['kind'] ?? 'file') === 'pdf' ? 'PDF' : 'Image' }}
                                                        @if (! empty($doc['status']))
                                                            · {{ display_label($doc['status'], 'document_status') ?: $doc['status'] }}
                                                        @endif
                                                    </p>
                                                    <div class="mt-2">
                                                        <x-admin.document-preview
                                                            :url="$doc['url']"
                                                            :label="$item['evidence']['documents_open_label'] ?? 'Open document'"
                                                            variant="button" />
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="px-3.5 py-4 text-sm text-rose-800">
                                        {{ ($item['evidence_type'] ?? '') === 'activity'
                                            ? 'No activity proof documents uploaded for this person yet.'
                                            : 'No statement uploaded for this person yet.' }}
                                    </p>
                                @endif
                            </div>
                        @endif
                        @if ((! empty($item['evidence']['photos']) || ! empty($item['evidence']['photo_pairs'])) && ($item['evidence']['layout'] ?? null) !== 'documents')
                            @php
                                $photoLayout = $item['evidence']['layout'] ?? null;
                                $facePhoto = collect($item['evidence']['photos'])->firstWhere('role', 'face');
                                $idPhoto = collect($item['evidence']['photos'])->firstWhere('role', 'id');
                                $supportPhotos = collect($item['evidence']['photos'])->where('role', 'face_support')->values();
                                $evidenceAssets = collect($item['evidence']['assets'] ?? []);
                            @endphp
                            <div x-data="{
                                     lightbox: null,
                                     assetTab: {{ (int) ($evidenceAssets->first()['id'] ?? 0) }},
                                     open(url, label) { this.lightbox = { url, label } },
                                     close() { this.lightbox = null }
                                 }">
                                @if ($photoLayout === 'photo_pairs' && ! empty($item['evidence']['photo_pairs']))
                                    <div class="rounded-xl ring-2 ring-brand/20 overflow-hidden bg-white">
                                        <div class="px-3 py-2 bg-brand-muted/40 border-b border-brand/10">
                                            <p class="text-[11px] font-bold text-brand uppercase tracking-widest">Asset photo · Valuer photo</p>
                                            <p class="text-[11px] text-gray-600 mt-0.5">Same angle, side by side — including owner with asset.</p>
                                        </div>
                                        @if ($evidenceAssets->count() > 1)
                                            <div class="flex gap-1.5 overflow-x-auto px-3 py-2 border-b border-gray-100">
                                                @foreach ($evidenceAssets as $evAsset)
                                                    <button type="button" @click="assetTab = {{ (int) $evAsset['id'] }}"
                                                            class="shrink-0 rounded-lg px-3 py-1.5 text-[11px] font-semibold"
                                                            :class="assetTab === {{ (int) $evAsset['id'] }} ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'">
                                                        {{ $evAsset['label'] ?? 'Asset' }}
                                                    </button>
                                                @endforeach
                                            </div>
                                            @foreach ($evidenceAssets as $evAsset)
                                                <div x-show="assetTab === {{ (int) $evAsset['id'] }}" x-cloak class="divide-y divide-gray-100">
                                                    @foreach ($evAsset['photo_pairs'] ?? [] as $pair)
                                                        @include('admin.loan-applications.review._photo_pair_row', ['pair' => $pair])
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="divide-y divide-gray-100">
                                                @foreach ($item['evidence']['photo_pairs'] as $pair)
                                                    @include('admin.loan-applications.review._photo_pair_row', ['pair' => $pair])
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @elseif ($photoLayout === 'face_id_compare' && ($facePhoto || $idPhoto))
                                    <div class="rounded-xl ring-2 ring-brand/20 overflow-hidden bg-white">
                                        <div class="px-3 py-2 bg-brand-muted/40 border-b border-brand/10">
                                            <p class="text-[11px] font-bold text-brand uppercase tracking-widest">Primary check — face vs uploaded ID</p>
                                            <p class="text-[11px] text-gray-600 mt-0.5">CRB does not return a portrait. Compare our face capture to the ID the borrower uploaded.</p>
                                        </div>
                                        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                                            <button type="button" class="p-3 text-left hover:bg-gray-50/80 transition"
                                                    @if (! empty($facePhoto['url']))
                                                        @click="open(@js($facePhoto['url']), @js($facePhoto['label'] ?? 'Face'))"
                                                    @endif>
                                                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-2">1. Face capture</p>
                                                @if (! empty($facePhoto['url']))
                                                    <img src="{{ $facePhoto['url'] }}" alt="{{ $facePhoto['label'] }}" class="w-full max-h-56 object-cover rounded-lg ring-1 ring-gray-200">
                                                    <span class="text-[11px] font-semibold text-brand mt-1.5 inline-block">Enlarge</span>
                                                @else
                                                    <div class="h-40 grid place-items-center rounded-lg bg-rose-50 text-sm text-rose-700 ring-1 ring-rose-100">Face not uploaded</div>
                                                @endif
                                            </button>
                                            <button type="button" class="p-3 text-left hover:bg-gray-50/80 transition"
                                                    @if (! empty($idPhoto['url']))
                                                        @click="open(@js($idPhoto['url']), @js($idPhoto['label'] ?? 'ID'))"
                                                    @endif>
                                                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-2">2. Uploaded ID</p>
                                                @if (! empty($idPhoto['url']))
                                                    <img src="{{ $idPhoto['url'] }}" alt="{{ $idPhoto['label'] }}" class="w-full max-h-56 object-cover rounded-lg ring-1 ring-gray-200">
                                                    <span class="text-[11px] font-semibold text-brand mt-1.5 inline-block">{{ $idPhoto['label'] }} · Enlarge</span>
                                                @else
                                                    <div class="h-40 grid place-items-center rounded-lg bg-rose-50 text-sm text-rose-700 ring-1 ring-rose-100">ID card not on file</div>
                                                @endif
                                            </button>
                                        </div>
                                    </div>
                                    @if ($supportPhotos->isNotEmpty())
                                        <p class="text-[11px] font-semibold text-gray-500 mt-2">Supporting angles</p>
                                        <div class="flex gap-2 overflow-x-auto pb-1 mt-1">
                                            @foreach ($supportPhotos as $photo)
                                                <button type="button"
                                                        @click="open(@js($photo['url']), @js($photo['label'] ?? 'Photo'))"
                                                        class="shrink-0 w-20 h-20 rounded-xl overflow-hidden ring-1 ring-gray-200 bg-gray-50 hover:ring-brand transition text-left">
                                                    <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}" class="w-full h-full object-cover">
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <div class="flex gap-2 overflow-x-auto pb-1">
                                        @foreach ($item['evidence']['photos'] as $photo)
                                            <button type="button"
                                                    @click="open(@js($photo['url']), @js($photo['label'] ?? 'Photo'))"
                                                    class="shrink-0 w-28 h-28 rounded-xl overflow-hidden ring-1 ring-gray-200 bg-gray-50 hover:ring-brand transition text-left relative">
                                                <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}" class="w-full h-full object-cover">
                                                <span class="absolute inset-x-0 bottom-0 bg-black/55 text-white text-[9px] font-semibold px-1.5 py-1 truncate">{{ $photo['label'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                <div x-show="lightbox" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70"
                                     @keydown.escape.window="close()"
                                     @click.self="close()">
                                    <div class="relative max-w-4xl w-full max-h-[90vh] rounded-2xl overflow-hidden bg-black shadow-2xl ring-1 ring-white/20">
                                        <button type="button" @click="close()"
                                                class="absolute top-3 right-3 z-10 rounded-full bg-black/60 text-white text-sm font-bold px-3 py-1.5 hover:bg-black/80">
                                            Close
                                        </button>
                                        <img :src="lightbox?.url" :alt="lightbox?.label || 'Photo'"
                                             class="w-full max-h-[90vh] object-contain bg-black">
                                        <p class="absolute bottom-0 inset-x-0 px-4 py-2 text-xs text-white/90 bg-gradient-to-t from-black/80 to-transparent"
                                           x-text="lightbox?.label"></p>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if (! empty($item['evidence']['compare']))
                            <div class="rounded-xl ring-1 ring-brand/15 overflow-hidden">
                                <div class="grid grid-cols-[1.1fr_1fr_1fr] gap-0 bg-brand-muted/40 px-3 py-2 text-[10px] uppercase tracking-widest font-semibold text-brand">
                                    <span>Field</span>
                                    <span>Profile</span>
                                    <span>CRB</span>
                                </div>
                                <ul class="divide-y divide-gray-100 bg-white">
                                    @foreach ($item['evidence']['compare'] as $row)
                                        @php
                                            $tone = match ($row['status'] ?? '') {
                                                'match' => 'bg-emerald-50/50',
                                                'mismatch' => 'bg-amber-50/70',
                                                'missing' => 'bg-slate-50/80',
                                                default => 'bg-white',
                                            };
                                        @endphp
                                        <li class="grid grid-cols-[1.1fr_1fr_1fr] gap-2 px-3 py-2.5 text-sm {{ $tone }}">
                                            <span class="text-xs font-semibold text-gray-600 self-center">{{ $row['label'] }}</span>
                                            <span class="font-semibold text-gray-900 break-words">{{ $row['profile'] }}</span>
                                            <span class="font-semibold text-gray-900 break-words">{{ $row['crb'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (! empty($item['evidence']['rows']))
                            <dl class="grid sm:grid-cols-2 gap-2">
                                @foreach ($item['evidence']['rows'] as $row)
                                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ $row['label'] }}</dt>
                                        <dd class="text-sm font-semibold text-gray-900 mt-0.5 break-words">{{ $row['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        @if (! empty($item['captures_statement']) && empty($item['auto_na']))
            <div class="rounded-xl ring-1 ring-brand/15 bg-brand-muted/30 p-3.5 space-y-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">Statement totals</p>
                    <p class="text-[11px] text-gray-600 mt-0.5">
                        Confirm the statement covers at least 6 months, then enter total deposits and Save. The system decides pass or fail. Period is always 6 months.
                    </p>
                </div>
                <input type="hidden" name="{{ $fieldBase }}[statement_months]" value="6">
                <label class="block">
                    <span class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Total deposits (TZS) · 6 months</span>
                    <input type="text"
                           inputmode="decimal"
                           autocomplete="off"
                           name="{{ $fieldBase }}[statement_deposits_total]"
                           value="{{ \App\Support\MoneyFormat::forInput($item['statement_deposits_total'] ?? null) }}"
                           data-money-input="0"
                           @if (! $canEdit) disabled @endif
                           placeholder="e.g. 6,000,000"
                           class="mt-1 w-full rounded-lg border-0 text-sm ring-1 ring-brand/15 px-3 py-2 focus:ring-2 focus:ring-brand/30">
                </label>
                <p class="text-[11px] text-gray-600">
                    If this statement covers less than 6 months, do not enter deposits. Request a new 6-month statement instead.
                </p>
                @if ($canEdit)
                    <button type="button"
                            @click="window.dispatchEvent(new CustomEvent('kf-open-doc-composer', { detail: { labels: ['Updated Bank Statement'] } })); $nextTick(() => document.getElementById('request-more-documents')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                            class="inline-flex items-center rounded-lg bg-white text-brand text-[11px] font-bold px-2.5 py-1.5 ring-1 ring-brand/20 hover:bg-brand-muted/40">
                        Statement is shorter than 6 months — request a new file
                    </button>
                @endif
            </div>
                        @endif

                        @if (empty($item['captures_statement']) && empty($item['read_only']) && empty($item['awaiting_data']) && empty($item['catalog_system']))
                        <div x-show="verdict === 'fail'" x-cloak class="rounded-xl bg-rose-50/80 ring-1 ring-rose-100 p-3 space-y-2">
                            @if (($item['risk'] ?? '') === 'critical' || ($item['gate'] ?? null) === 'statements_vs_declared')
                                <div class="rounded-lg bg-rose-100 ring-1 ring-rose-200 px-3 py-2">
                                    <p class="text-xs font-bold text-rose-950">Failing this check rejects the application</p>
                                    <p class="text-[11px] text-rose-900 mt-0.5">
                                        Pick a reason and Save — the system opens Decision with rejection letter reasons filled in
                                        (for group loans, one member Fail can reject the whole file).
                                    </p>
                                </div>
                            @endif
                            <label class="block text-[10px] uppercase tracking-widest text-rose-800 font-semibold">
                                {{ ($item['item_key'] ?? '') === 'bank_or_mobile_money' ? 'Concerning pattern (required)' : 'Fail reason (required)' }}
                            </label>
                            <select name="{{ $fieldBase }}[fail_reason_code]" x-model="reason"
                                    class="w-full rounded-lg border-rose-200 text-sm focus:border-rose-400 focus:ring-rose-200">
                                <option value="">{{ ($item['item_key'] ?? '') === 'bank_or_mobile_money' ? 'Select concerning pattern…' : 'Select reason…' }}</option>
                                @foreach ($item['fail_reasons'] as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <textarea name="{{ $fieldBase }}[fail_reason_custom]" rows="2" x-show="reason === 'custom'" x-cloak
                                      class="w-full rounded-lg border-rose-200 text-sm"
                                      placeholder="Explain the fail reason…">{{ $item['fail_reason_custom'] ?? '' }}</textarea>
                        </div>
                        @endif

                        @if ($item['verdict'] === 'fail' && ($item['fail_reason_label'] ?? null))
                            <p class="text-[11px] text-rose-800 font-medium">Recorded: {{ $item['fail_reason_label'] }}</p>
                        @endif
                        @if ($item['by_name'] || $item['at'])
                            <p class="text-[11px] text-gray-400">
                                @if ($item['by_name']){{ $item['by_name'] }}@endif
                                @if ($item['at'])
                                    · {{ \Illuminate\Support\Carbon::parse($item['at'])->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                                @endif
                            </p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endforeach
