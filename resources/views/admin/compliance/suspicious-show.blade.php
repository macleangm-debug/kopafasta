<x-admin.layout
    title="Suspicious Activity #{{ $activity->id }}"
    heading="Suspicious Activity #{{ $activity->id }}"
    subheading="{{ $activity->activity_type }}">

    <div class="mb-4">
        <a href="{{ route('admin.compliance.aml-reports') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to AML reports</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 text-emerald-800 text-sm px-4 py-2">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Details --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Activity details</h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs uppercase text-gray-500">Severity</dt>
                    <dd>
                        <span @class([
                            'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase',
                            'bg-red-100 text-red-800'      => $activity->severity === 'critical',
                            'bg-orange-100 text-orange-800'=> $activity->severity === 'high',
                            'bg-amber-100 text-amber-800'  => $activity->severity === 'medium',
                            'bg-gray-100 text-gray-700'    => $activity->severity === 'low',
                        ])>{{ $activity->severity }}</span>
                    </dd>
                </div>
                <div><dt class="text-xs uppercase text-gray-500">Status</dt><dd class="font-semibold">{{ ucfirst($activity->status) }}</dd></div>
                <div><dt class="text-xs uppercase text-gray-500">Amount</dt>
                    <dd class="font-semibold">{{ $activity->amount !== null ? 'TZS '.format_number((float)$activity->amount) : '—' }}</dd></div>
                <div><dt class="text-xs uppercase text-gray-500">Detected</dt><dd>{{ optional($activity->detected_at)->format('Y-m-d H:i') }}</dd></div>
                <div><dt class="text-xs uppercase text-gray-500">Rule triggered</dt><dd>{{ optional($activity->rule)->name ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase text-gray-500">Assigned to</dt><dd>{{ optional($activity->assignee)->name ?? '—' }}</dd></div>
            </dl>
            <hr class="my-4 border-gray-200">
            <div class="text-sm">
                <div class="text-xs uppercase text-gray-500 mb-1">Description</div>
                <p class="whitespace-pre-line">{{ $activity->description }}</p>
            </div>

            @if ($activity->customer)
            <hr class="my-4 border-gray-200">
            <div class="text-sm">
                <div class="text-xs uppercase text-gray-500 mb-1">Customer</div>
                <p class="font-semibold">{{ trim(($activity->customer->first_name ?? '').' '.($activity->customer->last_name ?? '')) }}</p>
                <p class="text-gray-600">{{ $activity->customer->phone ?? '' }} {{ $activity->customer->email ? ' · '.$activity->customer->email : '' }}</p>
                @if ($activity->customer->is_pep) <span class="inline-block mt-1 px-2 py-0.5 bg-red-100 text-red-800 text-xs rounded font-semibold">PEP</span> @endif
            </div>
            @endif
        </div>

        {{-- Update form + SAR --}}
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.compliance.suspicious.update', $activity) }}"
                  class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                @csrf
                @method('PATCH')
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Update investigation</h3>
                <label class="block text-xs uppercase text-gray-500 mb-1">Status</label>
                <select name="status" class="w-full mb-3 rounded-lg border-gray-300 text-sm">
                    @foreach (['open','investigating','cleared','reported','closed'] as $s)
                        <option value="{{ $s }}" @selected($activity->status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <label class="block text-xs uppercase text-gray-500 mb-1">Investigator notes</label>
                <textarea name="investigator_notes" rows="6"
                          class="w-full rounded-lg border-gray-300 text-sm">{{ old('investigator_notes', $activity->investigator_notes) }}</textarea>
                <button class="mt-3 w-full inline-flex justify-center items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-lg">
                    Save
                </button>
            </form>

            <form method="POST" action="{{ route('admin.compliance.suspicious.sar', $activity) }}"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js('File a SAR?'),
                      message: @js('File a SAR and mark this activity as reported?'),
                      confirmLabel: @js('File SAR'),
                      confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                      tone: 'warning',
                  })"
                  class="bg-white rounded-xl shadow-sm ring-1 ring-red-200 p-6">
                @csrf
                <h3 class="text-sm font-semibold text-red-700 mb-2">File Suspicious Activity Report</h3>
                <p class="text-xs text-gray-600 mb-3">Generates a SAR PDF for FIU submission and marks this activity as <em>reported</em>.</p>
                <button class="w-full inline-flex justify-center items-center gap-1.5 text-sm font-semibold text-white bg-red-700 hover:bg-red-800 px-4 py-2 rounded-lg">
                    Download SAR PDF
                </button>
            </form>
        </div>
    </div>
</x-admin.layout>
