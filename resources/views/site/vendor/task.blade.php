<x-site.vendor-layout title="Task detail" active="tasks">
    @php
        $badge = match ($task->status) {
            'assigned'    => 'bg-amber-100 text-amber-700',
            'in_progress' => 'bg-indigo-100 text-indigo-700',
            'completed'   => 'bg-emerald-100 text-emerald-700',
            'rejected'    => 'bg-red-100 text-red-700',
            'cancelled'   => 'bg-gray-100 text-gray-600',
            default       => 'bg-gray-100 text-gray-600',
        };
    @endphp

    <div class="mb-5">
        <a href="{{ route('site.vendor.tasks') }}" class="text-sm text-indigo-600 hover:underline">← Back to tasks</a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold">{{ ucfirst(str_replace('_',' ', $task->task_type)) }}</h1>
            <p class="text-xs text-gray-500">Task #{{ $task->id }} · Created {{ $task->created_at->format('d M Y') }}</p>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ', $task->status) }}</span>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Left: details --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <h2 class="font-bold mb-3">Customer & location</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500 text-xs">Customer</dt><dd class="font-medium">{{ $task->customer_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Phone</dt><dd class="font-medium">{{ $task->customer_phone ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 text-xs">Vehicle</dt><dd class="font-medium">{{ $task->vehicle_details ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 text-xs">Location</dt><dd class="font-medium">{{ $task->location ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Due</dt><dd class="font-medium">{{ $task->due_at ? $task->due_at->format('d M Y H:i') : '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Fee</dt><dd class="font-medium">TZS {{ number_format($task->fee_amount) }}</dd></div>
                </dl>
                @if ($task->instructions)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Instructions</p>
                        <p class="text-sm whitespace-pre-line">{{ $task->instructions }}</p>
                    </div>
                @endif
            </div>

            {{-- Upload proof --}}
            @if ($task->status !== 'completed' && $task->status !== 'cancelled')
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="font-bold mb-3">Upload proof</h2>
                    <form method="POST" action="{{ route('site.vendor.task.proof', $task) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">What is it? (e.g. Installation photo)</label>
                            <input name="label" required class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">File (image or PDF, max 5MB)</label>
                            <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                        </div>
                        <button class="rounded-lg bg-indigo-600 text-white text-sm font-semibold px-4 py-2 hover:bg-indigo-700">Upload</button>
                    </form>

                    @if ($task->documents->isNotEmpty())
                        <ul class="mt-5 divide-y divide-gray-100">
                            @foreach ($task->documents as $d)
                                <li class="py-2 flex items-center justify-between text-sm">
                                    <span class="truncate">{{ $d->label }}</span>
                                    <a href="{{ asset('storage/'.$d->file_path) }}" target="_blank" class="text-indigo-600 hover:underline text-xs">View</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Complete task --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h2 class="font-bold mb-3">Mark complete</h2>
                    <form method="POST" action="{{ route('site.vendor.task.complete', $task) }}" class="space-y-3">
                        @csrf
                        @if (str_contains($task->task_type, 'gps'))
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">GPS serial number</label>
                                <input name="gps_serial" value="{{ $task->gps_serial }}" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                        @endif
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Notes (optional)</label>
                            <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ $task->notes }}</textarea>
                        </div>
                        <button class="rounded-lg bg-emerald-600 text-white text-sm font-semibold px-4 py-2 hover:bg-emerald-700">Complete task</button>
                    </form>
                </div>
            @else
                @if ($task->documents->isNotEmpty())
                    <div class="rounded-2xl border border-gray-200 bg-white p-5">
                        <h2 class="font-bold mb-3">Uploaded proof</h2>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($task->documents as $d)
                                <li class="py-2 flex items-center justify-between text-sm">
                                    <span class="truncate">{{ $d->label }}</span>
                                    <a href="{{ asset('storage/'.$d->file_path) }}" target="_blank" class="text-indigo-600 hover:underline text-xs">View</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>

        {{-- Right: actions --}}
        <div class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <h3 class="font-bold mb-3">Actions</h3>
                <div class="space-y-2">
                    @if ($task->status === 'assigned')
                        <form method="POST" action="{{ route('site.vendor.task.accept', $task) }}">
                            @csrf
                            <button class="w-full rounded-lg bg-indigo-600 text-white text-sm font-semibold py-2 hover:bg-indigo-700">Accept task</button>
                        </form>
                    @endif
                    @if (in_array($task->status, ['assigned', 'in_progress']))
                        <form method="POST" action="{{ route('site.vendor.task.start', $task) }}">
                            @csrf
                            <button class="w-full rounded-lg bg-gray-900 text-white text-sm font-semibold py-2 hover:bg-black">Start work</button>
                        </form>
                    @endif
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 text-xs space-y-1 text-gray-500">
                    @if ($task->accepted_at)<p>Accepted {{ $task->accepted_at->format('d M H:i') }}</p>@endif
                    @if ($task->started_at)<p>Started {{ $task->started_at->format('d M H:i') }}</p>@endif
                    @if ($task->completed_at)<p>Completed {{ $task->completed_at->format('d M H:i') }}</p>@endif
                </div>
            </div>

            @if ($task->payment)
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <h3 class="font-bold mb-3">Payment</h3>
                    <p class="text-sm"><span class="text-gray-500">Invoice:</span> <span class="font-mono">{{ $task->payment->invoice_number }}</span></p>
                    <p class="text-sm"><span class="text-gray-500">Amount:</span> TZS {{ number_format($task->payment->amount) }}</p>
                    @php $pc = $task->payment->status === 'paid' ? 'emerald' : 'amber'; @endphp
                    <p class="mt-1"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ $task->payment->status }}</span></p>
                    <a href="{{ route('site.vendor.invoice', $task->payment) }}" class="block mt-3 text-sm text-indigo-600 hover:underline">View invoice →</a>
                </div>
            @endif
        </div>
    </div>
</x-site.vendor-layout>
