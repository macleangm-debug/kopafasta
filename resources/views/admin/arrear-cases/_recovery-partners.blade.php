@php
    $policy = app(\App\Services\RecoveryPolicyService::class);
@endphp

<div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
    <div class="flex items-center justify-between gap-3 mb-4">
        <h2 class="text-sm font-semibold text-gray-900">Recovery partners</h2>
        <a href="{{ route('admin.recovery.partners.index') }}" class="text-xs font-semibold text-brand hover:underline">Manage partners</a>
    </div>

    @if (! empty($recoveryAssignments) && $recoveryAssignments->isNotEmpty())
        <ul class="divide-y divide-gray-100 mb-4 text-sm">
            @foreach ($recoveryAssignments as $assignment)
                <li class="py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold">{{ $assignment->vendor?->name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ display_label($assignment->partner_type, 'recovery_partner_type') }}
                            · SLA {{ $assignment->sla_due_at?->format('d M') ?? '—' }}
                        </p>
                    </div>
                    <a href="{{ route('admin.recovery.assignments.show', $assignment) }}" class="text-xs text-amber-700 font-semibold">View</a>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.arrear-cases.recovery-assign', $arrearCase) }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Partner type</label>
            <select name="partner_type" required class="w-full rounded-lg border-gray-300 text-sm">
                @foreach ($recoveryPartnerTypes as $type => $label)
                    <option value="{{ $type }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Partner</label>
            <select name="vendor_id" required class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">— Select partner —</option>
                @foreach ($recoveryPartners as $partner)
                    <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
            <textarea name="notes" rows="2" maxlength="2000" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Assignment instructions"></textarea>
        </div>
        <p class="text-[11px] text-gray-500">
            Commission is calculated from original outstanding at assignment — not compounded across partners.
        </p>
        <button type="submit" class="w-full text-sm font-semibold text-white bg-brand hover:bg-brand-light px-3 py-2 rounded-lg">
            Assign recovery partner
        </button>
    </form>
</div>
