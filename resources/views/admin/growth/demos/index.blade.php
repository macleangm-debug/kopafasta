<x-admin.layout title="Demo Accounts" heading="Demo Accounts" subheading="Create a marketing demo in under a minute. Isolated from real customers, ledgers and payments.">
    <div class="flex justify-end mb-4">
        @can('marketing.demos.create')
            <a href="{{ route('admin.growth.demos.create') }}" class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5">+ Create Demo</a>
        @endcan
    </div>
    <div class="hidden md:block overflow-x-auto rounded-2xl bg-white ring-1 ring-brand/10">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Who</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Expires</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($demos as $demo)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-semibold"><a class="text-brand hover:underline" href="{{ route('admin.growth.demos.show', $demo) }}">{{ $demo->display_name }}</a></td>
                        <td class="px-4 py-3">{{ $demo->who }}</td>
                        <td class="px-4 py-3">{{ $demo->status }}</td>
                        <td class="px-4 py-3 text-xs">{{ optional($demo->expires_at)->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3"><a class="text-xs font-semibold text-brand" href="{{ route('admin.growth.demos.show', $demo) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No demos yet. Create one for a screenshot or affiliate walkthrough.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="md:hidden space-y-3">
        @forelse ($demos as $demo)
            <a href="{{ route('admin.growth.demos.show', $demo) }}" class="block rounded-2xl bg-white ring-1 ring-gray-200 p-4">
                <p class="font-semibold">{{ $demo->display_name }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $demo->who }} · {{ $demo->status }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-500">No demos yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $demos->links() }}</div>
</x-admin.layout>
