@props([
    'name' => 'confirm',
    'title' => 'Are you sure you want to proceed?',
    'message' => null,
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'confirmClass' => 'bg-gray-900 hover:bg-gray-800 text-white',
])

<div
    x-data="{
        open: false,
        form: null,
        title: @js($title),
        message: @js($message),
        confirmLabel: @js($confirmLabel),
        confirmClass: @js($confirmClass),
        defaults: {
            title: @js($title),
            message: @js($message),
            confirmLabel: @js($confirmLabel),
            confirmClass: @js($confirmClass),
        },
    }"
    x-on:open-confirm-{{ $name }}.window="
        open = true;
        form = $event.detail?.form ?? null;
        title = $event.detail?.title ?? defaults.title;
        message = $event.detail?.message ?? defaults.message;
        confirmLabel = $event.detail?.confirmLabel ?? defaults.confirmLabel;
        confirmClass = $event.detail?.confirmClass ?? defaults.confirmClass;
    "
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900" x-text="title"></h3>
        <p x-show="message" x-cloak class="text-sm text-gray-600 mt-2" x-text="message"></p>
        <div class="mt-6 flex gap-3 justify-end">
            <button type="button" @click="open = false"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200">
                {{ $cancelLabel }}
            </button>
            <button type="button"
                    @click="if (form) { form.submit(); } open = false;"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold"
                    :class="confirmClass"
                    x-text="confirmLabel"></button>
        </div>
    </div>
</div>
