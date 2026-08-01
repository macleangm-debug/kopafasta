<x-admin.layout
    title="New user"
    heading="New user"
    subheading="Create an admin or staff account"
    :backUrl="route('admin.users.index')"
    backLabel="Back">

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 ring-1 ring-red-200 p-4 text-sm text-red-700">
                    <strong class="block mb-1">Please fix the following:</strong>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('admin.users._form', ['record' => null])

            <div class="flex items-center justify-between gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800 px-4 py-2">Cancel</a>
                <button type="submit"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg shadow-sm transition">
                    Create user
                </button>
            </div>
        </form>
    </div>
</x-admin.layout>
