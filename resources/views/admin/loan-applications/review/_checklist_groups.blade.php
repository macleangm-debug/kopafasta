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
                    <span>{{ $group['label'] }}</span>
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
                        reason: @js($item['fail_reason_code'] ?? '')
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
                                                <svg class="size-3.5 text-gray-400 transition" :class="openItem === @js($itemKey) ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                            </p>
                            @if ($item['evidence']['hint'] ?? null)
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $item['evidence']['hint'] }}</p>
                            @endif
                            @if ($mismatchCount > 0)
                                <p class="text-[11px] font-semibold text-amber-700 mt-0.5">{{ $mismatchCount }} difference{{ $mismatchCount === 1 ? '' : 's' }} vs CRB — expand to compare</p>
                            @endif
                        </button>
                        <div class="flex flex-wrap gap-1.5 shrink-0">
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
                        </div>
                    </div>

                    <div x-show="openItem === @js($itemKey)" x-cloak class="mt-3 space-y-3">
                        @if (! empty($item['evidence']['photos']))
                            @php
                                $photoLayout = $item['evidence']['layout'] ?? null;
                                $facePhoto = collect($item['evidence']['photos'])->firstWhere('role', 'face');
                                $idPhoto = collect($item['evidence']['photos'])->firstWhere('role', 'id');
                                $supportPhotos = collect($item['evidence']['photos'])->where('role', 'face_support')->values();
                            @endphp
                            <div x-data="{
                                     lightbox: null,
                                     open(url, label) { this.lightbox = { url, label } },
                                     close() { this.lightbox = null }
                                 }">
                                @if ($photoLayout === 'face_id_compare' && ($facePhoto || $idPhoto))
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

                        <div x-show="verdict === 'fail'" x-cloak class="rounded-xl bg-rose-50/80 ring-1 ring-rose-100 p-3 space-y-2">
                            <label class="block text-[10px] uppercase tracking-widest text-rose-800 font-semibold">Fail reason (required)</label>
                            <select name="{{ $fieldBase }}[fail_reason_code]" x-model="reason"
                                    class="w-full rounded-lg border-rose-200 text-sm focus:border-rose-400 focus:ring-rose-200">
                                <option value="">Select reason…</option>
                                @foreach ($item['fail_reasons'] as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <textarea name="{{ $fieldBase }}[fail_reason_custom]" rows="2" x-show="reason === 'custom'" x-cloak
                                      class="w-full rounded-lg border-rose-200 text-sm"
                                      placeholder="Explain the fail reason…">{{ $item['fail_reason_custom'] ?? '' }}</textarea>
                        </div>

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
