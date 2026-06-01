<nav class="sticky top-14 z-30 -mx-1 mb-6 bg-gray-50/95 backdrop-blur border-y border-gray-200 py-2 px-1" aria-label="Review sections">
    <div class="flex gap-1 overflow-x-auto">
        @foreach ([
            ['#review-workflow', 'Workflow'],
            ['#review-borrower', 'Borrower'],
            ['#review-verification', 'Verification'],
            ['#review-documents', 'Documents'],
            ['#review-guarantors', 'Guarantors'],
            ['#review-crb', 'CRB'],
            ['#review-contract', 'Contract'],
            ['#review-history', 'History'],
        ] as [$href, $label])
            <a href="{{ $href }}"
               class="shrink-0 px-3 py-2 text-xs font-semibold rounded-lg text-gray-600 hover:text-gray-900 hover:bg-white ring-1 ring-transparent hover:ring-gray-200 transition">
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
