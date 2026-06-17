<x-site.borrower-layout :title="brand_title(__('borrower.kyc_page.title'))" active="kyc" content-width="wide">

    @php
        $statusColor = match ($kyc->status) {
            'approved'  => ['bg' => 'bg-emerald-50', 'ring' => 'ring-emerald-200', 'text' => 'text-emerald-700', 'badge' => 'bg-emerald-100 text-emerald-700', 'bar' => 'bg-emerald-500'],
            'rejected'  => ['bg' => 'bg-red-50',     'ring' => 'ring-red-200',     'text' => 'text-red-700',     'badge' => 'bg-red-100 text-red-700',         'bar' => 'bg-red-500'],
            'in_review' => ['bg' => 'bg-blue-50',    'ring' => 'ring-blue-200',    'text' => 'text-blue-700',    'badge' => 'bg-blue-100 text-blue-700',       'bar' => 'bg-blue-500'],
            default     => ['bg' => 'bg-amber-50',   'ring' => 'ring-amber-200',   'text' => 'text-amber-700',   'badge' => 'bg-amber-100 text-amber-700',     'bar' => 'bg-amber-500'],
        };
        $customerKindKey = match ($customer->type ?? 'individual') {
            'business' => 'business',
            'group'    => 'group',
            default    => 'individual',
        };
        $customerKind = __('borrower.kyc_page.kinds.'.$customerKindKey);
        $statusLabel   = ucfirst(str_replace('_', ' ', $kyc->status));
        $requiredCount = $required ?? $types->count();
        $uploadedCount = $uploaded ?? $uploads->keys()->count();
        $progressPct   = $progress ?? ($requiredCount > 0 ? (int) round(($uploadedCount / $requiredCount) * 100) : 0);
        $missingList   = $missing ?? collect();
    @endphp

    <div class="flex items-start justify-between gap-3 mb-6 flex-wrap">
        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.kyc_page.eyebrow') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold">{{ __('borrower.kyc_page.title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{!! __('borrower.kyc_page.subtitle', ['kind' => '<strong>'.$customerKind.'</strong>']) !!}</p>
        </div>
        <div class="flex flex-col items-end gap-2">
            <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-gray-100 text-gray-700">{{ __('borrower.kyc_page.account_type', ['kind' => $customerKind]) }}</span>
            <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $statusColor['badge'] }}">{{ __('borrower.kyc_page.status_badge', ['status' => $statusLabel]) }}</span>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-6 rounded-2xl {{ $statusColor['bg'] }} ring-1 {{ $statusColor['ring'] }} p-5">
        @if ($kyc->status === 'approved')
            <p class="font-semibold {{ $statusColor['text'] }}">{{ __('borrower.kyc_page.approved_title') }}</p>
            <p class="text-sm {{ $statusColor['text'] }} opacity-80 mt-1">{{ __('borrower.kyc_page.verified_on', ['date' => optional($kyc->verified_at)->format('d M Y') ?? '—']) }}</p>
        @elseif ($kyc->status === 'rejected')
            <p class="font-semibold {{ $statusColor['text'] }}">{{ __('borrower.kyc_page.rejected_title') }}</p>
            <p class="text-sm {{ $statusColor['text'] }} opacity-80 mt-1">{{ __('borrower.kyc_page.reupload_hint') }}</p>
        @elseif ($kyc->status === 'in_review')
            <p class="font-semibold {{ $statusColor['text'] }}">{{ __('borrower.kyc_page.under_review') }}</p>
            <p class="text-sm {{ $statusColor['text'] }} opacity-80 mt-1">{{ __('borrower.kyc_page.under_review_hint') }}</p>
        @else
            <p class="font-semibold {{ $statusColor['text'] }}">{{ __('borrower.kyc_page.upload_required') }}</p>
            <p class="text-sm {{ $statusColor['text'] }} opacity-80 mt-1">{{ __('borrower.kyc_page.upload_progress', ['uploaded' => $uploadedCount, 'required' => $requiredCount]) }}</p>
        @endif

        <div class="mt-4">
            <div class="flex items-center justify-between text-xs {{ $statusColor['text'] }} mb-1">
                <span class="font-semibold">{{ __('borrower.kyc_page.progress') }}</span>
                <span class="font-mono font-bold">{{ $progressPct }}%</span>
            </div>
            <div class="h-2.5 bg-white/70 rounded-full overflow-hidden">
                <div class="h-full {{ $statusColor['bar'] }} transition-all" style="width: {{ $progressPct }}%"></div>
            </div>
        </div>

        @if ($missingList->isNotEmpty() && $kyc->status !== 'approved')
            <div class="mt-4 text-xs {{ $statusColor['text'] }}">
                <p class="font-semibold mb-1">{{ __('borrower.kyc_page.still_needed', ['count' => $missingList->count()]) }}</p>
                <ul class="grid sm:grid-cols-2 gap-x-4 gap-y-0.5 opacity-90">
                    @foreach ($missingList as $m)
                        <li class="flex items-start gap-1.5">
                            <span class="mt-1 inline-block w-1.5 h-1.5 rounded-full {{ $statusColor['bar'] }}"></span>
                            <span>{{ $m->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @if ($types->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ __('borrower.kyc_page.no_types') }}
        </div>
    @else
        <div class="grid lg:grid-cols-2 gap-6">
            @foreach ($types as $type)
                @php
                    $myUploads = $uploads[$type->id] ?? collect();
                    $latest = $myUploads->first();
                    $isApproved = $latest && in_array($latest->status, ['verified', 'approved']);
                    $isRejected = $latest && $latest->status === 'rejected';
                @endphp
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $type->name }}</p>
                            @if (!empty($type->code))
                                <p class="text-xs text-gray-500 font-mono">{{ $type->code }}</p>
                            @endif
                        </div>
                        @if ($latest)
                            @php
                                $badge = match ($latest->status) {
                                    'verified','approved' => 'bg-emerald-100 text-emerald-700',
                                    'rejected'            => 'bg-red-100 text-red-700',
                                    default               => 'bg-amber-100 text-amber-700',
                                };
                            @endphp
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ ucfirst(str_replace('_',' ',$latest->status)) }}</span>
                        @else
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-gray-100 text-gray-600">{{ __('borrower.kyc_page.not_uploaded') }}</span>
                        @endif
                    </div>

                    <div class="p-5 space-y-3">
                        @if ($latest)
                            <div class="text-xs text-gray-500">
                                {{ __('borrower.kyc_page.last_uploaded', ['time' => \Carbon\Carbon::parse($latest->created_at)->diffForHumans()]) }}
                                @if ($latest->file_path)
                                    · <a href="{{ asset('storage/'.$latest->file_path) }}" target="_blank" class="text-amber-600 hover:underline">{{ __('borrower.kyc_page.view_file') }}</a>
                                @endif
                            </div>
                            @if ($isRejected && $latest->notes)
                                <p class="text-xs text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2">{{ $latest->notes }}</p>
                            @endif
                        @endif

                        @if (!$isApproved)
                            <form method="POST" action="{{ route('site.borrower.kyc.store') }}" enctype="multipart/form-data" class="space-y-3 pt-2 border-t border-gray-100">
                                @csrf
                                <input type="hidden" name="document_type_id" value="{{ $type->id }}">
                                <input type="file" name="file" accept="image/*,application/pdf" required class="w-full text-sm">
                                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2.5 rounded-full text-sm">
                                    {{ $latest ? __('borrower.kyc_page.reupload') : __('borrower.kyc_page.upload') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-8 text-center">
        <p class="text-xs text-gray-500">{{ __('borrower.kyc_page.tips') }}</p>
    </div>

</x-site.borrower-layout>
