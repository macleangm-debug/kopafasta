@props(['active' => 'under_review'])
@php
    $tabs = [
        'under_review' => ['Applications', 'admin.loan-applications.pipeline.under-review'],
        'approved'     => ['Approved Loans', 'admin.loan-applications.pipeline.approved'],
        'disbursement' => ['Disbursement', 'admin.loan-applications.pipeline.disbursement'],
    ];
@endphp
<nav class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 pb-3">
    @foreach ($tabs as $key => [$label, $route])
        <a href="{{ route($route) }}"
           class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $active === $key ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
            {{ $label }}
        </a>
    @endforeach
    <a href="{{ route('admin.loan-applications.index') }}"
       class="ml-auto px-3 py-1.5 rounded-md text-sm font-medium text-gray-500 hover:text-gray-800">
        All applications →
    </a>
</nav>
