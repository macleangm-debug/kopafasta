<x-site.vendor-layout title="Documents" active="documents">
    <h1 class="text-2xl font-extrabold mb-1">Documents</h1>
    <p class="text-sm text-gray-500 mb-5">All proofs and files you have uploaded.</p>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="font-bold mb-3">Upload new</h2>
            <form method="POST" action="{{ route('site.vendor.documents.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Label</label>
                    <input name="label" required class="w-full rounded-lg border-gray-300 text-sm" placeholder="Certificate, ID copy…">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">File (jpg/png/pdf, 5MB)</label>
                    <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                </div>
                <button class="w-full rounded-lg bg-indigo-600 text-white text-sm font-semibold py-2 hover:bg-indigo-700">Upload</button>
            </form>
        </div>

        <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="font-bold mb-3">My uploads</h2>
            @if ($documents->isEmpty())
                <p class="text-sm text-gray-500">No documents yet.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($documents as $d)
                        <li class="py-3 flex items-center justify-between text-sm">
                            <div class="min-w-0">
                                <p class="font-semibold truncate">{{ $d->label }}</p>
                                <p class="text-xs text-gray-500 truncate">
                                    @if ($d->task)Task #{{ $d->task->id }} · @endif{{ $d->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <a href="{{ asset('storage/'.$d->file_path) }}" target="_blank" class="text-indigo-600 hover:underline text-xs font-semibold shrink-0 ml-3">View</a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4">{{ $documents->links() }}</div>
            @endif
        </div>
    </div>
</x-site.vendor-layout>
