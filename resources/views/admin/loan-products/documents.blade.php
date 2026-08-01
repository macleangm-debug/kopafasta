<x-admin.layout title="Required Documents" heading="Required Documents" subheading="KYC and credit documents required per product">
    @php($products = \App\Models\LoanProduct::query()->with('requirements')->orderBy('code')->get())

    <div class="mb-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
        Add or edit document requirements on each product’s <strong>Edit</strong> page — last step: <strong>Documents</strong>.
    </div>

    <div class="space-y-4">
        @forelse ($products as $p)
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-semibold text-gray-900">{{ $p->name }}</div>
                        <div class="text-xs text-gray-500 font-mono">{{ $p->code }}</div>
                    </div>
                    <a href="{{ route('admin.loan-products.edit', $p) }}" class="text-xs font-medium text-brand hover:text-brand-light">Edit requirements →</a>
                </div>

                @if ($p->requirements->isNotEmpty())
                    <ul class="text-sm space-y-1">
                        @foreach ($p->requirements as $req)
                            <li class="flex items-center gap-2">
                                <span class="inline-block size-1.5 rounded-full {{ $req->is_required ? 'bg-brand-gold' : 'bg-gray-300' }}"></span>
                                <span>{{ $req->name }}</span>
                                @if ($req->description)
                                    <span class="text-xs text-gray-400">— {{ $req->description }}</span>
                                @endif
                                @if ($req->is_required)
                                    <span class="text-[10px] uppercase tracking-wider text-amber-700">required</span>
                                @else
                                    <span class="text-[10px] uppercase tracking-wider text-gray-400">optional</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-sm text-gray-500 flex flex-wrap gap-2 items-center">
                        <span class="text-xs text-gray-400 italic">No custom requirements configured.</span>
                        @if ($p->requires_collateral)
                            <span class="px-2 py-1 rounded bg-amber-100 text-amber-800 text-xs">Collateral flag set</span>
                        @endif
                        @if ($p->requires_guarantor)
                            <span class="px-2 py-1 rounded bg-amber-100 text-amber-800 text-xs">Guarantor flag set</span>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-xl ring-1 ring-gray-200 px-5 py-12 text-center text-gray-500">
                No loan products configured.
                <a href="{{ route('admin.loan-products.create') }}" class="block mt-2 text-amber-700 font-medium hover:text-brand-light">Create a product</a>
            </div>
        @endforelse
    </div>
</x-admin.layout>
