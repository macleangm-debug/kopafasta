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
    <div class="space-y-3">
        @if ($layout === 'face_id_compare' && ($facePhoto || $idPhoto))
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="text-left"
                        @if (! empty($facePhoto['url']))
                            onclick="window.kfOpenDocumentPreview(@js($facePhoto['url']), @js($facePhoto['label'] ?? 'Face capture'), 'image')"
                        @endif>
                    <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-500 mb-1">Face capture</p>
                    @if (! empty($facePhoto['url']))
                        <img src="{{ $facePhoto['url'] }}" alt="{{ $facePhoto['label'] ?? 'Face' }}" class="w-full h-28 object-cover rounded-lg ring-1 ring-slate-200">
                    @else
                        <div class="h-28 grid place-items-center rounded-lg bg-rose-50 text-xs text-rose-800 ring-1 ring-rose-100 px-2 text-center">Face not uploaded</div>
                    @endif
                </button>
                <button type="button" class="text-left"
                        @if (! empty($idPhoto['url']))
                            onclick="window.kfOpenDocumentPreview(@js($idPhoto['url']), @js($idPhoto['label'] ?? 'National ID'), 'image')"
                        @endif>
                    <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-500 mb-1">National ID portrait</p>
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
            <div class="grid grid-cols-2 gap-2">
                @foreach ($photos as $photo)
                    @if (empty($photo['url']))
                        @continue
                    @endif
                    <button type="button"
                            onclick="window.kfOpenDocumentPreview(@js($photo['url']), @js($photo['label'] ?? 'Photo'), @js($photo['kind'] ?? 'image'))"
                            class="text-left rounded-xl overflow-hidden ring-1 ring-slate-200 bg-slate-50">
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] ?? 'Photo' }}" class="w-full h-28 object-cover">
                        <p class="px-2 py-1.5 text-[11px] font-semibold text-slate-700 truncate">{{ $photo['label'] ?? 'View document' }}</p>
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
                @foreach ($docs as $doc)
                    @if (empty($doc['url']))
                        @continue
                    @endif
                    <article class="rounded-xl ring-1 ring-slate-200 px-3 py-3 space-y-1">
                        <p class="text-sm font-bold text-slate-900">{{ $doc['label'] ?? 'Document' }}</p>
                        @if (! empty($doc['type_label']))
                            <p class="text-xs text-slate-600">{{ $doc['type_label'] }}</p>
                        @endif
                        @if (! empty($doc['owner']))
                            <p class="text-xs text-slate-600">{{ $doc['owner'] }}</p>
                        @endif
                        @if (! empty($doc['uploaded_at']))
                            <p class="text-xs text-slate-500">Uploaded {{ $doc['uploaded_at'] }}</p>
                        @endif
                        @if (! empty($doc['request_label']))
                            <p class="text-xs text-slate-500">Request: {{ $doc['request_label'] }}</p>
                        @endif
                        <button type="button"
                                onclick="window.kfOpenDocumentPreview(@js($doc['url']), @js($doc['label'] ?? 'Document'), @js($doc['kind'] ?? null))"
                                class="text-sm font-bold text-brand underline">
                            View document
                        </button>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endif
