@php
    $photos = $snapshot['asset_photos'] ?? [];
    $insurance = $snapshot['insurance_documents'] ?? [];
    $ownership = $snapshot['ownership_documents'] ?? [];
    $hasMedia = $photos !== [] || $insurance !== [] || $ownership !== [];
@endphp

@if ($hasMedia)
    <div class="rounded-xl ring-1 ring-gray-200 p-4 space-y-4">
        <h3 class="text-sm font-semibold text-gray-900">{{ $heading ?? 'Asset documents' }}</h3>

        @if ($photos !== [])
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Asset photos</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ($photos as $photo)
                        @if (! empty($photo['url']))
                            <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="group block">
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] ?? 'Asset photo' }}"
                                     class="aspect-square w-full rounded-lg object-cover ring-1 ring-gray-200 group-hover:ring-amber-400">
                                <p class="mt-1 text-[11px] text-gray-600 truncate">{{ $photo['label'] ?? 'Photo' }}</p>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @foreach ([['Insurance', $insurance], ['Ownership', $ownership]] as [$groupLabel, $docs])
            @if ($docs !== [])
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ $groupLabel }} documents</p>
                    <ul class="space-y-2">
                        @foreach ($docs as $doc)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span>{{ $doc['label'] ?? 'Document' }}</span>
                                @if (! empty($doc['url']))
                                    <a href="{{ $doc['url'] }}" target="_blank" rel="noopener"
                                       class="text-xs font-semibold text-amber-700 hover:underline shrink-0">
                                        {{ ! empty($doc['is_image']) ? 'View image' : 'View document' }} ↗
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </div>
@endif
