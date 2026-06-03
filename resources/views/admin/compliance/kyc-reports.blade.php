<x-admin.layout title="KYC Reports" heading="KYC Reports" subheading="Customer verification snapshot">

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        @php
            $cards = [
                ['Total customers', $stats['total_customers'], 'bg-indigo-50 text-indigo-700'],
                ['KYC verified',    $stats['verified'],       'bg-emerald-50 text-emerald-700'],
                ['KYC pending',     $stats['pending'],        'bg-amber-50 text-amber-700'],
                ['KYC rejected',    $stats['rejected'],       'bg-rose-50 text-rose-700'],
                ['High-risk',       $stats['high_risk'],      'bg-rose-50 text-rose-700'],
                ['PEP flagged',     $stats['pep_flagged'],    'bg-amber-50 text-amber-700'],
                ['Blacklisted',     $stats['blacklisted'],    'bg-gray-100 text-gray-700'],
                ['Dormant (90d)',   $stats['dormant_90d'],    'bg-gray-50 text-gray-700'],
            ];
        @endphp
        @foreach ($cards as [$lbl, $val, $cls])
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">{{ $lbl }}</div>
                <div class="mt-2 text-3xl font-bold {{ $cls }} inline-block rounded-md px-3">{{ format_number($val) }}</div>
            </div>
        @endforeach
    </div>
</x-admin.layout>
