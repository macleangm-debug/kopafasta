@props([
    'documents',
    'uploadRoute',
    'canUpload' => true,
])

@if (session('status'))
    <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif

<div class="grid lg:grid-cols-3 gap-6">
    @if ($canUpload)
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 h-fit">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_account.upload_new') }}</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1 mb-4">{{ __('site.partner_account.add_document') }}</h2>
            <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.doc_label') }}</label>
                    <input name="label" required maxlength="80"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand"
                           placeholder="{{ __('site.partner_account.doc_label_placeholder') }}">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.doc_file') }}</label>
                    <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                </div>
                <button type="submit" class="w-full rounded-xl bg-brand-gold hover:brightness-95 text-brand text-sm font-bold py-2.5">
                    {{ __('site.partner_account.upload') }}
                </button>
            </form>
            <p class="text-xs text-gray-500 mt-3">{{ __('site.partner_account.docs_admin_hint') }}</p>
        </div>
    @endif

    <div @class(['glass-card rounded-2xl ring-1 ring-brand/10 p-5', 'lg:col-span-2' => $canUpload, 'lg:col-span-3' => ! $canUpload])>
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_account.my_documents') }}</p>
        <h2 class="text-lg font-bold text-gray-900 mt-1 mb-4">{{ __('site.partner_account.uploaded_files') }}</h2>
        @if ($documents->isEmpty())
            <p class="text-sm text-gray-500 py-8 text-center">{{ __('site.partner_account.no_documents') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($documents as $doc)
                    <li class="py-3.5 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $doc->label }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                @if ($doc->task ?? null){{ __('site.partner_account.task_ref', ['id' => $doc->task->id]) }} · @endif
                                {{ $doc->created_at?->diffForHumans() }}
                            </p>
                        </div>
                        <x-site.document-view-button :url="asset('storage/'.$doc->file_path)" :label="__('site.partner_account.view')" class="text-brand hover:underline text-xs font-semibold shrink-0" />
                    </li>
                @endforeach
            </ul>
            @if (method_exists($documents, 'links'))
                <div class="mt-4">{{ $documents->links() }}</div>
            @endif
        @endif
    </div>
</div>
