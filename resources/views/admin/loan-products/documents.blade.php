<x-admin.layout title="Required Documents" heading="Required Documents" subheading="KYC and credit documents required per product">
    @php($products = \App\Models\LoanProduct::query()->with('requirements')->orderBy('code')->get())

    <div class="space-y-4">
        @forelse ($products as $p)
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-semibold text-gray-900">{{ $p->name }}</div>
                        <div class="text-xs text-gray-500 font-mono">{{ $p->code }}</div>
                    </div>
                    <a href="{{ route('admin.loan-products.show', $p) }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">Manage →</a>
                </div>

                @if ($p->requirements->isNotEmpty())
                    <ul class="text-sm space-y-1">
                        @foreach ($p->requirements as $req)
                            <li class="flex items-center gap-2">
                                <span class="inline-block size-1.5 rounded-full {{ $req->is_required ? 'bg-amber-500' : 'bg-gray-300' }}"></span>
                                <span>{{ $req->document_type ?? $req->name ?? 'Document' }}</span>
                                @if ($req->is_required)
                                    <span class="text-[10px] uppercase tracking-wider text-amber-700">required</span>
                                @else
                                    <span class="text-[10px] uppercase tracking-wider text-gray-400">optional</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-sm text-gray-500 flex flex-wrap gap-2">
                        <span class="px-2 py-1 rounded bg-gray-100 text-xs">National ID</span>
                        <span class="px-2 py-1 rounded bg-gray-100 text-xs">Proof of income</span>
                        @if ($p->requires_collateral)
                            <span class="px-2 py-1 rounded bg-amber-100 text-amber-800 text-xs">Collateral document</span>
                        @endif
                        @if ($p->requires_guarantor)
                            <span class="px-2 py-1 rounded bg-amber-100 text-amber-800 text-xs">Guarantor form</span>
                        @endif
                        <span class="text-xs text-gray-400 italic">(no custom requirements configured)</span>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-xl ring-1 ring-gray-200 px-5 py-12 text-center text-gray-500">
                No loan products configured.
            </div>
        @endforelse
    </div>
</x-admin.layout>
