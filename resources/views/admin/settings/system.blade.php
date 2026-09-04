<x-admin.layout title="System" heading="System" subheading="Which environment and build you are looking at">
    <div class="max-w-xl space-y-4">
        <dl class="rounded-2xl bg-white ring-1 ring-brand/10 divide-y divide-gray-100">
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Environment</dt>
                <dd class="text-sm font-bold {{ $release['environment'] === 'staging' ? 'text-amber-700' : 'text-gray-900' }}">{{ $release['label'] }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Application version</dt>
                <dd class="text-sm font-semibold text-gray-900 font-mono">{{ $release['version'] }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Commit</dt>
                <dd class="text-sm font-semibold text-gray-900 font-mono">{{ $release['short_commit'] ?: 'unknown' }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Deployed</dt>
                <dd class="text-sm font-semibold text-gray-900">{{ $release['deployed_at_display'] ?: '—' }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">APP_URL</dt>
                <dd class="text-sm font-semibold text-gray-900 break-all">{{ $release['app_url'] }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">APP_DEBUG</dt>
                <dd class="text-sm font-semibold {{ $release['debug'] ? 'text-rose-700' : 'text-emerald-700' }}">{{ $release['debug'] ? 'true' : 'false' }}</dd>
            </div>
        </dl>
        <p class="text-xs text-gray-500">Git commit is the technical authority. Production must match the Staging commit you approved. Never copy selected PHP files between environments.</p>
    </div>
</x-admin.layout>
