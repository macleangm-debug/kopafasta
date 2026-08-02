<x-admin.layout
    title="New notification template"
    heading=""
    :backUrl="route('admin.notification-templates.index')"
    backLabel="All templates">

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-brand/10">
        <div class="bg-gradient-to-r from-brand via-brand to-brand-light px-6 py-5 text-white">
            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
            <h1 class="text-xl font-bold tracking-tight mt-1">New message — all languages</h1>
            <p class="text-sm text-white/75 mt-1">Pick the event, then write English and Kiswahili side by side.</p>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('admin.notification-templates.store') }}" class="space-y-6">
                @csrf

                @if ($errors->any())
                    <div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4 text-sm text-red-700">
                        <strong class="block mb-1">Please fix the following:</strong>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('admin.notification-templates._form', ['record' => null])

                <div class="flex items-center justify-between gap-3 pt-4 border-t border-gray-100">
                    <a href="{{ route('admin.notification-templates.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800 px-4 py-2">Cancel</a>
                    <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-xl shadow-sm">
                        Create all languages
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
