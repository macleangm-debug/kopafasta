<x-admin.layout
    title="Edit notification template"
    heading=""
    :backUrl="route('admin.notification-templates.index')"
    backLabel="All templates">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-brand/10">
        <div class="p-6">
            <form method="POST" action="{{ route('admin.notification-templates.update', $record) }}" class="space-y-6">
                @csrf
                @method('PUT')

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

                @include('admin.notification-templates._form')

                <div class="flex items-center justify-between gap-3 pt-4 border-t border-gray-100">
                    <a href="{{ route('admin.notification-templates.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800 px-4 py-2">Cancel</a>
                    <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-xl shadow-sm">
                        Save template
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
