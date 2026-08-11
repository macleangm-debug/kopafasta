@props(['groupProgress' => null])

@if (! empty($groupProgress) && ! empty($groupProgress['members']))
    <div id="group-member-progress" class="mb-6 glass-card overflow-hidden ring-1 ring-brand/20" x-data="{ openId: null }">
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
        <ul class="divide-y divide-gray-100">
            @foreach ($groupProgress['members'] as $index => $member)
                @php
                    $memberKey = 'gm-'.$index.'-'.($member['customer_id'] ?? 0).'-'.($member['invitation_id'] ?? 0);
                    $percent = (int) ($member['profile_percent'] ?? 0);
                    $sections = $member['profile_sections'] ?? [];
                    $avatarUrl = $member['avatar_url'] ?? null;
                @endphp
                <li class="px-5 py-4">
                    <button type="button"
                            class="w-full text-left flex flex-wrap items-center gap-3"
                            @click="openId = openId === @js($memberKey) ? null : @js($memberKey)">
                        <div class="size-10 rounded-2xl bg-brand text-white grid place-items-center text-sm font-bold shrink-0 overflow-hidden">
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $member['name'] ?? '' }}" class="size-full object-cover">
                            @else
                                {{ strtoupper(mb_substr((string) ($member['name'] ?? '?'), 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-sm text-gray-900 truncate">{{ $member['name'] ?? '—' }}</p>
                                @if (($member['role'] ?? '') === 'leader')
                                    <span class="inline-flex items-center rounded-full bg-brand text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5">
                                        {{ __('borrower.apply.group_members.leader_badge') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-brand-muted text-brand ring-1 ring-brand/15 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5">
                                        {{ __('borrower.apply.group_members.member_badge') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">{{ $member['phone'] ?? '' }}</p>
                            <div class="mt-2">
                                <div class="flex items-center justify-between gap-2 text-[11px] text-gray-500 mb-1">
                                    <span>{{ __('borrower.apply.group.profile_completion') }}</span>
                                    <span class="font-bold tabular-nums text-brand">{{ $percent }}%</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div @class([
                                        'h-full rounded-full',
                                        'bg-emerald-500' => $percent >= 100,
                                        'bg-brand' => $percent < 100,
                                    ]) style="width: {{ max(0, min(100, $percent)) }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-semibold text-gray-700">{{ $member['status_label'] ?? '—' }}</p>
                            @if (! empty($sections))
                                <svg class="w-4 h-4 text-gray-400 ml-auto mt-2 transition" :class="openId === @js($memberKey) && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                            @endif
                        </div>
                    </button>
                    @if (! empty($sections))
                        <div x-show="openId === @js($memberKey)" x-cloak class="mt-3 pl-0 sm:pl-13 space-y-2">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.group_members.readiness_title') }}</p>
                            @foreach ($sections as $section)
                                <div @class([
                                    'flex items-center justify-between gap-3 rounded-2xl px-3.5 py-2.5 ring-1',
                                    'bg-emerald-50/80 ring-emerald-100' => $section['complete'] ?? false,
                                    'bg-gray-50 ring-gray-100' => ! ($section['complete'] ?? false),
                                ])>
                                    <span class="text-sm font-medium text-gray-800">{{ $section['label'] ?? '' }}</span>
                                    <span @class([
                                        'text-[11px] font-bold uppercase tracking-wider',
                                        'text-emerald-700' => $section['complete'] ?? false,
                                        'text-amber-700' => ! ($section['complete'] ?? false),
                                    ])>
                                        {{ ($section['complete'] ?? false)
                                            ? __('borrower.apply.group_members.readiness_done')
                                            : __('borrower.apply.group_members.readiness_pending') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
