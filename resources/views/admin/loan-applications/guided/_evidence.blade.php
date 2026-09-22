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
    $holderTabs = [];
    $missing = [];

    $pushTab = function (?array $file, string $fallback) use (&$holderTabs, &$missing): void {
        if ($file === null) {
            $missing[] = $fallback;

            return;
        }
        $url = $file['url'] ?? null;
        if (blank($url)) {
            $missing[] = $file['label'] ?? $fallback;

            return;
        }
        $holderTabs[] = [
            'key' => 'doc-'.count($holderTabs),
            'label' => $file['label'] ?? $fallback,
            'url' => $url,
            'kind' => $file['kind'] ?? (str_contains(strtolower((string) $url), '.pdf') ? 'pdf' : 'image'),
            'eyebrow' => $file['type_label'] ?? $fallback,
            'reference' => $file['label'] ?? $fallback,
            'caption' => collect([$file['status'] ?? null, $file['type_label'] ?? null])->filter()->implode(' · '),
            'owner' => $file['owner'] ?? null,
            'uploaded_at' => $file['uploaded_at'] ?? null,
        ];
    };

    if ($layout === 'face_id_compare') {
        $pushTab(is_array($facePhoto) ? $facePhoto : null, 'Face capture');
        $pushTab(is_array($idPhoto) ? $idPhoto : null, 'National ID portrait');
    } elseif ($pairs->isNotEmpty()) {
        foreach ($pairs as $pair) {
            foreach (['borrower' => 'Borrower', 'valuer' => 'Valuer'] as $side => $sideLabel) {
                $url = $pair[$side]['url'] ?? null;
                $pushTab(filled($url) ? [
                    'url' => $url,
                    'label' => trim(($pair['label'] ?? 'Evidence').' · '.$sideLabel),
                    'kind' => 'image',
                    'type_label' => $sideLabel,
                ] : ['label' => trim(($pair['label'] ?? 'Evidence').' · '.$sideLabel)], $sideLabel);
            }
        }
    } else {
        foreach ($photos as $photo) {
            $pushTab(is_array($photo) ? $photo : null, 'Document');
        }
    }
    foreach ($docs as $doc) {
        $pushTab(is_array($doc) ? $doc : null, 'Document');
    }
@endphp
@if ($holderTabs !== [] || $missing !== [] || $compare->isNotEmpty())
    <div class="space-y-3">
        @if ($holderTabs !== [])
            <x-admin.document-holder :tabs="$holderTabs" :expanded="false" />
        @endif
        @foreach ($missing as $label)
            <p class="text-xs font-semibold text-amber-800">{{ $label }} — missing</p>
        @endforeach

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
    </div>
@endif
