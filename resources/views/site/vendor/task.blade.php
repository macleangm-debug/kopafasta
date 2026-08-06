<x-site.vendor-layout title="Task detail" active="tasks">
    @php
        $badge = match ($task->status) {
            'assigned'    => 'bg-amber-100 text-amber-700',
            'in_progress' => 'bg-indigo-100 text-brand',
            'completed'   => 'bg-emerald-100 text-emerald-700',
            'rejected'    => 'bg-red-100 text-red-700',
            'cancelled'   => 'bg-gray-100 text-gray-600',
            default       => 'bg-gray-100 text-gray-600',
        };
        $priority = $task->priorityMeta();
        $priorityBadge = match ($priority['tone']) {
            'red'    => 'bg-red-100 text-red-700',
            'amber'  => 'bg-amber-100 text-amber-700',
            'indigo' => 'bg-indigo-100 text-brand',
            default  => 'bg-gray-100 text-gray-600',
        };
        $loan = $task->loan;
        $application = $task->loanApplication;
        $asset = $application?->assetReservation?->asset;
        $taskMeta = [];
        if (is_string($task->notes)) {
            $decoded = json_decode($task->notes, true);
            $taskMeta = is_array($decoded) ? $decoded : [];
        }
        $collateralAsset = null;
        if (! empty($taskMeta['customer_asset_id'])) {
            $collateralAsset = \App\Models\CustomerAsset::query()->find($taskMeta['customer_asset_id']);
        }
        $assetProfile = $taskMeta['asset_profile'] ?? null;
        if (! $assetProfile && $collateralAsset) {
            $assetProfile = app(\App\Services\CollateralInsurancePartnerService::class)->assetProfilePayload($collateralAsset);
        }
    @endphp

    <div class="mb-5">
        <a href="{{ route('site.partner.tasks') }}" class="text-sm text-brand hover:underline">← Back to tasks</a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold">{{ ucfirst(str_replace('_',' ', $task->task_type)) }}</h1>
            <p class="text-xs text-gray-500">Task #{{ $task->id }} · Created {{ $task->created_at->format('d M Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $priorityBadge }}">{{ $priority['label'] }} priority</span>
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ', $task->status) }}</span>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Left: details --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-3">Task overview</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500 text-xs">Task type</dt><dd class="font-medium">{{ ucfirst(str_replace('_', ' ', $task->task_type)) }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Status</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $task->status) }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Due date</dt><dd class="font-medium">{{ $task->due_at ? $task->due_at->format('d M Y H:i') : '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Priority</dt><dd class="font-medium">{{ $priority['label'] }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Fee</dt><dd class="font-medium">{{ format_money($task->fee_amount) }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Completion</dt><dd class="font-medium">{{ $task->completed_at ? $task->completed_at->format('d M Y H:i') : 'Not completed' }}</dd></div>
                </dl>
            </div>

            @if ($task->task_type === 'vehicle_insurance' && $assetProfile)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">Collateral asset profile</h2>
                    <div class="flex gap-4 items-start">
                        @if (! empty($assetProfile['thumbnail']))
                            <img src="{{ $assetProfile['thumbnail'] }}" alt="" class="size-20 rounded-xl object-cover ring-1 ring-gray-200 shrink-0">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $assetProfile['type_label'] ?? '' }}</p>
                            <p class="text-lg font-extrabold text-gray-900">{{ $assetProfile['label'] ?? '—' }}</p>
                            @if (! empty($assetProfile['registration_number']))
                                <p class="text-sm text-gray-600 mt-1">Reg: {{ $assetProfile['registration_number'] }}</p>
                            @endif
                            @if (! empty($assetProfile['estimated_value']))
                                <p class="text-sm text-gray-600">Est. value: {{ format_money($assetProfile['estimated_value']) }}</p>
                            @endif
                        </div>
                    </div>
                    @if (! empty($assetProfile['details']) && is_array($assetProfile['details']))
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm border-t border-gray-100 pt-4">
                            @foreach ($assetProfile['details'] as $key => $value)
                                @continue(! filled($value))
                                <div>
                                    <dt class="text-gray-500 text-xs">{{ str_replace('_', ' ', ucfirst((string) $key)) }}</dt>
                                    <dd class="font-medium">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                    @if (! empty($taskMeta['insured_value']))
                        <div class="mt-4 pt-4 border-t border-gray-100 text-sm space-y-1">
                            <p><span class="text-gray-500">Insured value:</span> <span class="font-bold">{{ format_money($taskMeta['insured_value']) }}</span></p>
                            <p><span class="text-gray-500">Premium paid:</span> <span class="font-bold text-brand">{{ format_money($taskMeta['premium'] ?? $task->fee_amount) }}</span></p>
                            @if (! empty($taskMeta['payment_reference']))
                                <p><span class="text-gray-500">Payment ref:</span> <span class="font-mono text-xs">{{ $taskMeta['payment_reference'] }}</span></p>
                            @endif
                        </div>
                    @endif
                    @if (! empty($assetProfile['photos']))
                        <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-3 gap-2">
                            @foreach ($assetProfile['photos'] as $photo)
                                <img src="{{ $photo }}" alt="" class="aspect-square rounded-lg object-cover ring-1 ring-gray-200">
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @if ($loan || $application)
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">Related loan information</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        @if ($loan)
                            <div><dt class="text-gray-500 text-xs">Loan ID</dt><dd class="font-medium font-mono">{{ $loan->loan_number ?? '#'.$loan->id }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Loan status</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $loan->status) }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Customer</dt><dd class="font-medium">{{ $loan->customer?->name ?? $task->customer_name ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Customer phone</dt><dd class="font-medium">{{ $loan->customer?->phone ?? $task->customer_phone ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Loan amount</dt><dd class="font-medium">{{ format_money($loan->approved_amount ?? $loan->principal_amount) }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Outstanding balance</dt><dd class="font-medium">{{ format_money($loan->outstanding_balance) }}</dd></div>
                        @elseif ($application)
                            <div><dt class="text-gray-500 text-xs">Application ID</dt><dd class="font-medium">#{{ $application->id }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Application stage</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $application->status) }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Customer</dt><dd class="font-medium">{{ $application->customer?->name ?? $task->customer_name ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500 text-xs">Requested amount</dt><dd class="font-medium">{{ format_money($application->requested_amount) }}</dd></div>
                        @endif
                        @if ($asset)
                            <div class="col-span-2"><dt class="text-gray-500 text-xs">Asset financed</dt><dd class="font-medium">{{ $asset->title }} · {{ format_money($asset->asset_value) }}</dd></div>
                        @elseif ($task->vehicle_details)
                            <div class="col-span-2"><dt class="text-gray-500 text-xs">Asset / vehicle</dt><dd class="font-medium">{{ $task->vehicle_details }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif

            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h2 class="font-bold mb-3">Customer & location</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-gray-500 text-xs">Customer</dt><dd class="font-medium">{{ $task->customer_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Phone</dt><dd class="font-medium">{{ $task->customer_phone ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 text-xs">Vehicle</dt><dd class="font-medium">{{ $task->vehicle_details ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-500 text-xs">Location</dt><dd class="font-medium">{{ $task->location ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Due</dt><dd class="font-medium">{{ $task->due_at ? $task->due_at->format('d M Y H:i') : '—' }}</dd></div>
                    <div><dt class="text-gray-500 text-xs">Fee</dt><dd class="font-medium">{{ format_money($task->fee_amount) }}</dd></div>
                </dl>
                @if ($task->instructions)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Action required</p>
                        <p class="text-sm whitespace-pre-line">{{ $task->instructions }}</p>
                    </div>
                @endif
                @if ($task->due_at)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Submission deadline</p>
                        <p class="text-sm font-medium">{{ $task->due_at->format('d M Y H:i') }}</p>
                    </div>
                @endif
                @if ($task->documents->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-2">Required documents submitted</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($task->documents as $d)
                                <li class="flex items-center justify-between gap-2">
                                    <span>{{ $d->label }}</span>
                                    <x-site.document-view-button :url="asset('storage/'.$d->file_path)" label="View" class="text-brand hover:underline text-xs" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Upload proof --}}
            @if ($task->status !== 'completed' && $task->status !== 'cancelled')
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">{{ $task->task_type === 'asset_valuation' ? 'Upload inspection photos' : 'Upload proof' }}</h2>
                    <form method="POST" action="{{ route('site.partner.task.proof', $task) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">
                                {{ $task->task_type === 'asset_valuation' ? 'Photo label (e.g. Front view, Engine, Interior)' : 'What is it? (e.g. Installation photo)' }}
                            </label>
                            <input name="label" required class="w-full rounded-lg border-gray-300 focus:border-brand/500 focus:ring-brand/500 text-sm"
                                   placeholder="{{ $task->task_type === 'asset_valuation' ? 'Asset photo' : 'Proof label' }}">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">File (image or PDF, max 5MB)</label>
                            <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                        </div>
                        <button class="rounded-lg bg-brand text-white text-sm font-semibold px-4 py-2 hover:bg-brand-light">Upload</button>
                    </form>

                    @if ($task->documents->isNotEmpty())
                        <ul class="mt-5 divide-y divide-gray-100">
                            @foreach ($task->documents as $d)
                                <li class="py-2 flex items-center justify-between text-sm">
                                    <span class="truncate">{{ $d->label }}</span>
                                    <x-site.document-view-button :url="asset('storage/'.$d->file_path)" label="View" class="text-brand hover:underline text-xs" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Complete task --}}
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h2 class="font-bold mb-3">
                        @if ($task->task_type === 'asset_valuation')
                            Submit valuation report
                        @elseif ($task->task_type === 'vehicle_insurance')
                            Record insurance cover
                        @else
                            Mark complete
                        @endif
                    </h2>
                    @if ($task->task_type === 'asset_valuation')
                        <p class="text-xs text-gray-500 mb-4">Upload photos above, then enter market and forced sale values from your physical inspection.</p>
                    @elseif ($task->task_type === 'vehicle_insurance')
                        <p class="text-xs text-gray-500 mb-4">Enter policy details for this specific collateral. Expiry updates the owner’s asset profile automatically.</p>
                    @endif
                    <form method="POST" action="{{ route('site.partner.task.complete', $task) }}" class="space-y-3">
                        @csrf
                        @if ($task->task_type === 'asset_valuation')
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Market value (TZS)</label>
                                <input name="market_value" type="number" min="0" step="1000" required class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Forced sale value (TZS)</label>
                                <input name="forced_sale_value" type="number" min="0" step="1000" required class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                        @endif
                        @if ($task->task_type === 'vehicle_insurance')
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Insurance type</label>
                                <select name="insurance_type" class="w-full rounded-lg border-gray-300 text-sm" required>
                                    <option value="" disabled selected>Select cover type</option>
                                    <option value="comprehensive">Comprehensive (Bima kamili)</option>
                                    <option value="third_party">Third Party (Bima ya wahusika wengine)</option>
                                </select>
                                <p class="mt-1 text-[11px] text-gray-500">Must match the actual policy — mismatch can reject the loan for falsified documentation.</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Policy number</label>
                                <input name="insurance_policy_number" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <x-site.date-input
                                    name="insurance_expires_at"
                                    label="Expiry / cover deadline"
                                    :required="true"
                                    :min="now()->format('Y-m-d')"
                                    :max="now()->addYears(15)->format('Y-m-d')"
                                />
                            </div>
                        @endif
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
                    <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                        <h2 class="font-bold mb-3">Uploaded proof</h2>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($task->documents as $d)
                                <li class="py-2 flex items-center justify-between text-sm">
                                    <span class="truncate">{{ $d->label }}</span>
                                    <x-site.document-view-button :url="asset('storage/'.$d->file_path)" label="View" class="text-brand hover:underline text-xs" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>

        {{-- Right: actions --}}
        <div class="space-y-4">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <h3 class="font-bold mb-3">Actions</h3>
                <div class="space-y-2">
                    @if ($task->status === 'assigned')
                        <form method="POST" action="{{ route('site.partner.task.accept', $task) }}">
                            @csrf
                            <button class="w-full rounded-lg bg-brand text-white text-sm font-semibold py-2 hover:bg-brand-light">Accept task</button>
                        </form>
                    @endif
                    @if (in_array($task->status, ['assigned', 'in_progress']))
                        <form method="POST" action="{{ route('site.partner.task.start', $task) }}">
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
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                    <h3 class="font-bold mb-3">Payment</h3>
                    <p class="text-sm"><span class="text-gray-500">Invoice:</span> <span class="font-mono">{{ $task->payment->invoice_number }}</span></p>
                    <p class="text-sm"><span class="text-gray-500">Amount:</span> {{ format_money($task->payment->amount) }}</p>
                    @php $pc = $task->payment->status === 'paid' ? 'emerald' : 'amber'; @endphp
                    <p class="mt-1"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $pc }}-100 text-{{ $pc }}-700">{{ $task->payment->status }}</span></p>
                    <a href="{{ route('site.partner.invoice', $task->payment) }}" class="block mt-3 text-sm text-brand hover:underline">View invoice →</a>
                </div>
            @endif
        </div>
    </div>
</x-site.vendor-layout>
