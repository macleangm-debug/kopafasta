@php
    $evidence = $step['evidence'] ?? [];
    $photos = collect($evidence['photos'] ?? []);
    $pairs = collect($evidence['photo_pairs'] ?? []);
    $docs = collect($evidence['documents'] ?? []);
    $compare = collect($evidence['compare'] ?? []);
    $facePhoto = $photos->firstWhere('role', 'face');
    $idPhoto = $photos->firstWhere('role', 'id');
    $layout = $evidence['layout'] ?? null;
    $hasPhotos = $photos->isNotEmpty() || $pairs->isNotEmpty();
@endphp
@if ($hasPhotos || $docs->isNotEmpty() || $compare->isNotEmpty())
    <div class="space-y-3" x-data="{
            lightbox: null,
            pair: null,
            open(url, label) { this.pair = null; this.lightbox = { url, label } },
            openPair(left, right, label) { this.lightbox = null; this.pair = { left, right, label } },
            close() { this.lightbox = null; this.pair = null }
         }">
        @if ($layout === 'face_id_compare' && ($facePhoto || $idPhoto))
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="text-left" @if (! empty($facePhoto['url'])) @click="open(@js($facePhoto['url']), @js($facePhoto['label'] ?? 'Face'))" @endif>
                    <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-500 mb-1">Face capture</p>
                    @if (! empty($facePhoto['url']))
                        <img src="{{ $facePhoto['url'] }}" alt="{{ $facePhoto['label'] ?? 'Face' }}" class="w-full h-28 object-cover rounded-lg ring-1 ring-slate-200">
                    @else
                        <div class="h-28 grid place-items-center rounded-lg bg-rose-50 text-xs text-rose-800 ring-1 ring-rose-100 px-2 text-center">Face not uploaded</div>
                    @endif
                </button>
                <button type="button" class="text-left" @if (! empty($idPhoto['url'])) @click="open(@js($idPhoto['url']), @js($idPhoto['label'] ?? 'ID'))" @endif>
                    <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-500 mb-1">National ID</p>
                    @if (! empty($idPhoto['url']))
                        <img src="{{ $idPhoto['url'] }}" alt="{{ $idPhoto['label'] ?? 'ID' }}" class="w-full h-28 object-cover rounded-lg ring-1 ring-slate-200">
                    @else
                        <div class="h-28 grid place-items-center rounded-lg bg-rose-50 text-xs text-rose-800 ring-1 ring-rose-100 px-2 text-center">ID not on file</div>
                    @endif
                </button>
            </div>
        @elseif ($pairs->isNotEmpty())
            <div class="divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200 overflow-hidden">
                @foreach ($pairs->reject(fn ($pair) => ! empty($pair['extra']) || ! empty($pair['valuer_only'])) as $pair)
                    @include('admin.loan-applications.review._photo_pair_row', ['pair' => $pair, 'compare' => true])
                @endforeach
            </div>
        @elseif ($photos->isNotEmpty())
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($photos as $photo)
                    @if (empty($photo['url']))
                        @continue
                    @endif
                    <button type="button"
                            @click="open(@js($photo['url']), @js($photo['label'] ?? 'Photo'))"
                            class="shrink-0 w-24 h-24 rounded-xl overflow-hidden ring-1 ring-slate-200 bg-slate-50">
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] ?? 'Photo' }}" class="w-full h-full object-cover">
                    </button>
                @endforeach
            </div>
        @endif

        @if ($compare->isNotEmpty())
            <div class="rounded-xl ring-1 ring-slate-200 overflow-hidden">
                <p class="px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 bg-slate-50">View CRB details</p>
                <dl class="divide-y divide-slate-100">
                    @foreach ($compare as $row)
                        <div class="grid grid-cols-2 gap-2 px-3 py-2 text-sm">
                            <div>
                                <p class="text-[10px] uppercase text-slate-500">{{ $row['profile_source'] ?? 'File' }}</p>
                                <p class="font-semibold break-words">{{ $row['profile'] ?? $row['ours'] ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase text-slate-500">{{ $row['crb_source'] ?? 'CRB' }}</p>
                                <p class="font-semibold break-words">{{ $row['crb'] ?? $row['theirs'] ?? '—' }}</p>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        @if ($docs->isNotEmpty())
            <div class="space-y-2">
                <p class="text-xs font-bold text-slate-600">
                    {{ $docs->count() === 1 ? 'Preview document' : 'View documents ('.$docs->count().')' }}
                </p>
                @foreach ($docs as $doc)
                    @if (! empty($doc['url']))
                        <button type="button"
                                onclick="window.kfOpenDocumentPreview(@js($doc['url']), @js($doc['label'] ?? 'Document'), @js($doc['kind'] ?? null))"
                                class="block w-full text-left text-sm font-semibold text-brand underline break-words">
                            {{ $doc['label'] ?? 'Open document' }}
                        </button>
                    @endif
                @endforeach
            </div>
        @endif

        <div x-show="lightbox || pair" x-cloak
             class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/70"
             style="display: none;"
             @keydown.escape.window="close()"
             @click.self="close()">
            <div class="relative max-w-5xl w-full max-h-[90vh] rounded-2xl overflow-hidden bg-black">
                <button type="button" @click="close()"
                        class="absolute top-3 right-3 z-10 rounded-full bg-black/60 text-white text-sm font-bold px-3 py-1.5">Close</button>
                <template x-if="pair">
                    <div class="grid md:grid-cols-2 gap-px bg-white/10">
                        <div>
                            <p class="px-3 py-1.5 text-[11px] text-white/80">Borrower</p>
                            <img x-show="pair?.left" :src="pair?.left" alt="Borrower" class="w-full max-h-[80vh] object-contain">
                        </div>
                        <div>
                            <p class="px-3 py-1.5 text-[11px] text-white/80">Valuer</p>
                            <img x-show="pair?.right" :src="pair?.right" alt="Valuer" class="w-full max-h-[80vh] object-contain">
                        </div>
                    </div>
                </template>
                <template x-if="lightbox">
                    <div>
                        <img :src="lightbox?.url" :alt="lightbox?.label || 'Photo'" class="w-full max-h-[90vh] object-contain">
                        <button type="button" class="absolute bottom-3 left-3 rounded-lg bg-black/60 text-white text-xs font-bold px-3 py-1.5 sm:hidden"
                                @click="close()">Back to review</button>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endif
