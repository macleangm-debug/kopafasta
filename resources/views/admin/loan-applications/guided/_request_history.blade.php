@php
    $itemKey = (string) ($step['item_key'] ?? '');
    $history = $record->relationLoaded('documentRequests')
        ? $record->documentRequests
        : $record->documentRequests()->orderByDesc('id')->get();
    $related = $history->filter(function ($row) use ($itemKey, $step) {
        if ($itemKey !== '' && (string) $row->checklist_item === $itemKey) {
            return true;
        }
        $person = (string) ($step['participant']['person'] ?? '');
        $m = (int) ($step['participant']['m'] ?? 0);
        if ($person === 'member' && $m > 0) {
            return (int) $row->loan_group_member_id === $m;
        }

        return false;
    })->take(8);
@endphp
@if ($related->isNotEmpty())
    <details class="text-sm">
        <summary class="cursor-pointer font-semibold text-slate-600">View request history</summary>
        <ol class="mt-2 space-y-2">
            @foreach ($related as $req)
                @php
                    $lifecycle = is_array($req->lifecycle) ? $req->lifecycle : [];
                    $reminders = collect($lifecycle['reminders'] ?? [])->count();
                @endphp
                <li class="rounded-lg bg-slate-50 ring-1 ring-slate-200 px-3 py-2 text-xs text-slate-700">
                    <p class="font-bold text-slate-900">{{ $req->label }} · {{ ucfirst((string) $req->status) }}</p>
                    <p>Requested {{ format_app_datetime($req->created_at, 'd M Y') }}
                        @if ($req->due_at)
                            · Due {{ format_app_datetime($req->due_at, 'd M Y') }}
                        @endif
                        @if ($reminders > 0)
                            · Reminders {{ $reminders }}
                        @endif
                    </p>
                    @if ($req->request_reason)
                        <p>Reason: {{ $req->request_reason }}</p>
                    @endif
                    @if ($req->satisfied_at)
                        <p>Submitted {{ format_app_datetime($req->satisfied_at, 'd M Y') }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </details>
@endif
