<x-admin.layout title="Face verification" heading="Face verification" subheading="Review borrower face capture submissions">
<div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold">Pending review</h2>
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-800">{{ $pending->total() }}</span>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($pending as $customer)
                    <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ trim($customer->first_name.' '.$customer->last_name) }}</p>
                            <p class="text-xs text-gray-500">{{ $customer->phone ?? $customer->email ?? '—' }} · NIDA {{ $customer->national_id ?? '—' }}</p>
                        </div>
                        <a href="{{ route('admin.face-verifications.show', $customer) }}" class="text-sm font-semibold text-brand hover:text-brand-light">Review →</a>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-gray-500 text-center">No face verifications awaiting review.</p>
                @endforelse
            </div>
            @if ($pending->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">{{ $pending->links() }}</div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold">Recently reviewed</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($recent as $customer)
                    <div class="px-5 py-3">
                        <p class="text-sm font-medium">{{ trim($customer->first_name.' '.$customer->last_name) }}</p>
                        <p class="text-xs text-gray-500">{{ display_label($customer->face_verification_status, 'face_verification_status') }}</p>
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-gray-500">No recent reviews.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin.layout>
