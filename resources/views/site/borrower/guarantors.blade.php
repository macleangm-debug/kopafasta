<x-site.borrower-layout title="Guarantors — Kopafasta" active="guarantors">

    <h1 class="text-2xl font-bold mb-1">My guarantors</h1>
    <p class="text-sm text-gray-500 mb-6">A guarantor backs your loan. They sign on your behalf.</p>

    <div class="grid lg:grid-cols-3 gap-6">

        <form method="POST" action="{{ route('site.borrower.guarantors.store') }}"
              class="lg:col-span-1 bg-white rounded-2xl border border-gray-200 p-6">
            @csrf
            <h2 class="font-semibold mb-4">Add a guarantor</h2>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">First name</label>
                    <input name="first_name" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Last name</label>
                    <input name="last_name" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                </div>
            </div>

            <label class="block text-xs text-gray-600 mb-1 mt-3">Phone</label>
            <input name="phone" required placeholder="+255..." class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">

            <label class="block text-xs text-gray-600 mb-1 mt-3">Email (optional)</label>
            <input type="email" name="email" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">

            <label class="block text-xs text-gray-600 mb-1 mt-3">National ID (NIDA)</label>
            <input name="national_id" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">

            <label class="block text-xs text-gray-600 mb-1 mt-3">Address (optional)</label>
            <input name="address" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">

            <label class="block text-xs text-gray-600 mb-1 mt-3">Relationship</label>
            <select name="relationship" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                @foreach (['Spouse','Parent','Sibling','Friend','Colleague','Employer','Other'] as $r)
                    <option value="{{ $r }}">{{ $r }}</option>
                @endforeach
            </select>

            <button class="mt-5 w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Send guarantor request</button>
        </form>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="font-semibold">Linked guarantors</h2>
                <span class="text-xs text-gray-500">{{ $links->count() }}</span>
            </div>
            @if ($links->isEmpty())
                <div class="p-10 text-center text-sm text-gray-500">No guarantors yet.</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($links as $link)
                        @php
                            $g = $link->guarantor;
                            $color = match ($link->status) {
                                'accepted','verified' => 'bg-emerald-100 text-emerald-700',
                                'rejected'            => 'bg-red-100 text-red-700',
                                default               => 'bg-amber-100 text-amber-700',
                            };
                        @endphp
                        <li class="px-5 py-4 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-sm">{{ $g ? $g->first_name.' '.$g->last_name : 'Unknown' }}</p>
                                <p class="text-xs text-gray-500">{{ $g->phone ?? '—' }} · {{ $g->relationship ?? '—' }}</p>
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $color }}">{{ ucfirst($link->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

</x-site.borrower-layout>
