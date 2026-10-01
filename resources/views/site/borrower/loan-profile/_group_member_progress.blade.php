@props(['groupProgress' => null])

@if (! empty($groupProgress) && ! empty($groupProgress['members']))
    <div id="group-member-progress" class="mb-6 glass-card overflow-hidden ring-1 ring-brand/20">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 py-4 border-b border-brand/10">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group_members.your_team') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-1">
                        {{ __('borrower.apply.group.progress.profiles', [
                            'done' => $groupProgress['profiles_complete'] ?? 0,
                            'target' => $groupProgress['target'] ?? 0,
                        ]) }}
                    </p>
                    <div class="mt-3 max-w-xs">
                        <div class="flex items-center justify-between gap-2 text-[11px] text-gray-500 mb-1">
                            <span>{{ __('borrower.apply.group.progress.avg_completion_label') }}</span>
                            <span class="font-bold tabular-nums text-brand">{{ (int) ($groupProgress['avg_profile_percent'] ?? 0) }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand transition-all"
                                 style="width: {{ max(0, min(100, (int) ($groupProgress['avg_profile_percent'] ?? 0))) }}%"></div>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-extrabold text-brand tabular-nums">{{ ($groupProgress['added'] ?? 0) }}/{{ ($groupProgress['target'] ?? 0) }}</p>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.progress.added_label') }}</p>
                </div>
            </div>
        </div>

        <div class="px-5 py-5 space-y-4">
            @foreach ($groupProgress['members'] as $member)
                @php
                    $percent = (int) ($member['profile_percent'] ?? 0);
                    $steps = $member['progress_steps'] ?? [];
                    $terminal = (bool) ($member['terminal'] ?? false);
                    $badgeTone = (string) ($member['badge_tone'] ?? 'sky');
                    $role = (string) ($member['role'] ?? '');
                @endphp
                <x-site.invitee-progress
                    :name="$member['name'] ?? '—'"
                    :badge="$member['status_label'] ?? null"
                    :badge-tone="$badgeTone"
                    :steps="$steps"
                    :terminal="$terminal"
                    :terminal-label="$terminal ? ($member['status_label'] ?? null) : null"
                >
                    <div class="flex flex-wrap items-center gap-2 min-w-0">
                        <p class="text-lg sm:text-xl font-extrabold text-gray-900 tracking-tight truncate">{{ $member['name'] ?? '—' }}</p>
                        @if ($role === 'leader')
                            <span class="inline-flex items-center rounded-full bg-brand text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5">
                                {{ __('borrower.apply.group_members.leader_badge') }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-brand-muted text-brand ring-1 ring-brand/15 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5">
                                {{ __('borrower.apply.group_members.member_badge') }}
                            </span>
                        @endif
                    </div>
                    @if (! empty($member['phone']))
                        <p class="text-xs text-gray-500 mt-0.5">{{ $member['phone'] }}</p>
                    @endif
                </x-site.invitee-progress>
            @endforeach
        </div>
    </div>
@endif
