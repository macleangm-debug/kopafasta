<x-admin.layout title="Partner application" heading="Review application" :subheading="$application->categoryLabel().' · '.$application->full_name">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-4">
        <a href="{{ route('admin.partner-applications.index') }}" class="text-sm font-semibold text-amber-700 hover:underline">← All applications</a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Applicant</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">Contact name</dt>
                        <dd class="font-medium text-gray-900">{{ $application->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Applicant type</dt>
                        <dd class="font-medium text-gray-900">{{ ucfirst($application->applicant_category) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Email</dt>
                        <dd>{{ $application->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Phone</dt>
                        <dd>{{ $application->phone }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Primary region</dt>
                        <dd>{{ $application->region ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Coverage</dt>
                        <dd>{{ $application->coverage_regions ? implode(', ', $application->coverage_regions) : '—' }}</dd>
                    </div>
                </dl>
                @if ($application->message)
                    <div class="pt-2 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-1">Message</p>
                        <p class="text-sm text-gray-800 whitespace-pre-line">{{ $application->message }}</p>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Business profile</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">Trading name</dt>
                        <dd class="font-medium">{{ $application->business_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Legal name</dt>
                        <dd class="font-medium">{{ $application->legal_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Registration / BRELA</dt>
                        <dd>{{ $application->registration_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">TIN</dt>
                        <dd>{{ $application->tin ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-3">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Business documents</h2>
                @forelse ($application->documents as $doc)
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2.5 text-sm">
                        <div>
                            <p class="font-medium text-gray-900">{{ $doc->label() }}</p>
                            <p class="text-xs text-gray-500">{{ $doc->original_name }}</p>
                        </div>
                        <a href="{{ $doc->url() }}" target="_blank" class="text-xs font-semibold text-amber-700 hover:underline">Open</a>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No documents uploaded.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-4">Decision</h2>
                <form method="POST" action="{{ route('admin.partner-applications.update', $application) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border-gray-300 text-sm">
                            @foreach (['pending', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected($application->status === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Admin notes</label>
                        <textarea name="admin_notes" rows="4" class="w-full rounded-lg border-gray-300 text-sm">{{ old('admin_notes', $application->admin_notes) }}</textarea>
                    </div>
                    @unless ($application->partner_id)
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="convert" value="1" class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                   @checked(old('convert', $application->status === 'approved'))>
                            <span>Create partner account and send activation invite (requires Approved)</span>
                        </label>
                    @else
                        <p class="text-sm text-emerald-700">
                            Linked partner:
                            <a href="{{ route('admin.partners.show', $application->partner_id) }}" class="font-semibold underline">
                                {{ $application->partner?->vendor_number ?? '#'.$application->partner_id }}
                            </a>
                        </p>
                    @endunless
                    <button class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-xl px-4 py-2.5 text-sm">Save decision</button>
                </form>
            </div>
        </div>
    </div>
</x-admin.layout>
