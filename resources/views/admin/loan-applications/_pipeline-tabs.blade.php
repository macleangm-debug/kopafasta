@props(['active' => 'under_review'])
@php
    $tabs = [
        'under_review'   => ['Credit screening', 'admin.loan-applications.pipeline.under-review'],
        'system_sorted'  => ['System sorted', 'admin.loan-applications.pipeline.system-sorted'],
        'committee'      => ['Credit committee', 'admin.loan-applications.pre-approvals'],
        'approved'       => ['Management queue', 'admin.loan-applications.pipeline.approved'],
        'disbursement'   => ['Release queue', 'admin.loan-applications.pipeline.disbursement'],
    ];
    $role = (string) (auth()->user()?->role ?? '');
    if ($role === 'manager') {
        $tabs = array_intersect_key($tabs, array_flip(['approved', 'disbursement']));
    } elseif ($role === 'credit_committee') {
        $tabs = array_intersect_key($tabs, array_flip(['system_sorted', 'committee']));
    } elseif ($role === 'credit_analyst') {
        $tabs = array_intersect_key($tabs, array_flip(['under_review', 'system_sorted']));
    }
@endphp
<nav class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 pb-3">
    @foreach ($tabs as $key => [$label, $route])
        <a href="{{ route($route) }}"
           class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $active === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
            {{ $label }}
        </a>
    @endforeach
    <a href="{{ route('admin.loan-applications.index') }}"
       class="ml-auto px-3 py-1.5 rounded-md text-sm font-medium text-gray-500 hover:text-gray-800">
        All applications →
    </a>
</nav>
