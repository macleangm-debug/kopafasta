@props(['groupProgress' => null])

@if (! empty($groupProgress) && ! empty($groupProgress['members']))
    <div id="group-member-progress" class="mb-6 glass-card overflow-hidden ring-1 ring-brand/20">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 py-4 border-b border-brand/10">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group_members.your_team') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-1">
                        {{ __('borrower.apply.group.progress.profiles', [
                            'done' => $groupProgress['profiles_complete'] ?? 0,
                            'target' => $groupProgress['target'] ?? 0,
                        ]) }}
                        ·
                        {{ __('borrower.apply.group.progress.verified', [
                            'done' => $groupProgress['verified'] ?? 0,
                            'target' => $groupProgress['target'] ?? 0,
                        ]) }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.apply.group_members.steps_legend') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-extrabold text-brand tabular-nums">{{ ($groupProgress['added'] ?? 0) }}/{{ ($groupProgress['target'] ?? 0) }}</p>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.progress.added_label') }}</p>
                </div>
            </div>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($groupProgress['members'] as $member)
                <li class="px-5 py-4 flex flex-wrap items-center gap-3">
                    <div class="size-10 rounded-full bg-brand text-white grid place-items-center text-sm font-bold shrink-0">
                        {{ strtoupper(mb_substr((string) ($member['name'] ?? '?'), 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-sm text-gray-900 truncate">{{ $member['name'] ?? '—' }}</p>
                        <p class="text-xs text-gray-500">{{ $member['phone'] ?? '' }}</p>
                        @if (($member['role'] ?? '') === 'leader')
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mt-0.5">{{ __('borrower.apply.group_members.leader_badge') }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($member['progress_steps'] ?? [] as $step)
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-semibold ring-1',
                                'bg-emerald-50 text-emerald-800 ring-emerald-200' => $step['complete'] ?? false,
                                'bg-gray-50 text-gray-500 ring-gray-200' => ! ($step['complete'] ?? false),
                            ])>
                                {{ ($step['complete'] ?? false) ? '✓' : '○' }}
                                {{ $step['label'] ?? '' }}
                            </span>
                        @endforeach
                    </div>
                    <span class="text-xs font-semibold text-gray-700 shrink-0">{{ $member['status_label'] ?? '—' }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
