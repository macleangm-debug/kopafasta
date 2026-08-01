{{-- Document requirements repeater. Expects optional $requirements collection. --}}
@php
    $existing = collect(old('requirements', ($requirements ?? collect())->map(fn ($r) => [
        'id'          => $r->id ?? null,
        'name'        => $r->name ?? '',
        'description' => $r->description ?? '',
        'is_required' => (bool) ($r->is_required ?? true),
    ])->all()));

    if ($existing->isEmpty()) {
        $existing = collect([['id' => null, 'name' => '', 'description' => '', 'is_required' => true]]);
    }

    $nextIndex = $existing->count();
@endphp

<div id="product-requirements-editor" class="md:col-span-2 space-y-4" data-next-index="{{ $nextIndex }}">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Document requirements</h3>
        <p class="text-xs text-gray-500 mt-1">
            These appear on the borrower product-details screen, application uploads, and loan officer review.
        </p>
    </div>

    <div id="requirement-rows" class="space-y-3">
        @foreach ($existing as $i => $row)
            @include('admin.loan-products._requirement-row', ['index' => $i, 'row' => $row])
        @endforeach
    </div>

    <button type="button" id="add-requirement-row"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:text-brand-light">
        <span class="text-lg leading-none">+</span> Add document requirement
    </button>

    <template id="requirement-row-template">
        @include('admin.loan-products._requirement-row', [
            'index' => '__INDEX__',
            'row' => ['id' => null, 'name' => '', 'description' => '', 'is_required' => true],
        ])
    </template>
</div>

<script>
(function () {
    const editor = document.getElementById('product-requirements-editor');
    if (! editor || editor.dataset.bound === '1') return;
    editor.dataset.bound = '1';

    const rows = editor.querySelector('#requirement-rows');
    const tpl = editor.querySelector('#requirement-row-template');
    let nextIndex = parseInt(editor.dataset.nextIndex || '0', 10);

    function bindRow(row) {
        row.querySelector('[data-remove-requirement]')?.addEventListener('click', function () {
            if (rows.querySelectorAll('[data-requirement-row]').length <= 1) {
                row.querySelectorAll('input[type="text"], textarea').forEach(function (el) { el.value = ''; });
                row.querySelector('input[type="checkbox"]')?.setAttribute('checked', 'checked');
                return;
            }
            row.remove();
        });
    }

    rows.querySelectorAll('[data-requirement-row]').forEach(bindRow);

    editor.querySelector('#add-requirement-row')?.addEventListener('click', function () {
        const html = tpl.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        rows.appendChild(row);
        bindRow(row);
        nextIndex += 1;
    });
})();
</script>
