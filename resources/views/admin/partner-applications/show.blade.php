<x-admin.layout title="Partner application" heading="Review application" :subheading="$application->categoryLabel().' · '.$application->full_name">
    <div class="mb-4">
        <a href="{{ route('admin.partner-applications.index') }}" class="text-sm font-semibold text-brand hover:underline">← All applications</a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6" x-data="{ lightbox: null }">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden">
                <div class="bg-gradient-to-r from-brand-muted/60 to-white px-6 py-3 border-b border-brand/10">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-brand">Applicant</h2>
                </div>
                <dl class="p-6 grid sm:grid-cols-2 gap-4 text-sm">
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
                    <div class="px-6 pb-6 border-t border-gray-100 pt-4">
                        <p class="text-xs text-gray-500 mb-1">Message</p>
                        <p class="text-sm text-gray-800 whitespace-pre-line">{{ $application->message }}</p>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden">
                <div class="bg-gradient-to-r from-brand-muted/60 to-white px-6 py-3 border-b border-brand/10">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-brand">Business profile</h2>
                </div>
                <dl class="p-6 grid sm:grid-cols-2 gap-4 text-sm">
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

            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden">
                <div class="bg-gradient-to-r from-brand-muted/60 to-white px-6 py-3 border-b border-brand/10">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-brand">Business documents</h2>
                </div>
                <div class="p-6 space-y-3">
                    @forelse ($application->documents as $doc)
                        @php
                            $url = $doc->url();
                            $isImage = str_starts_with((string) ($doc->mime ?? ''), 'image/')
                                || preg_match('/\.(jpe?g|png|webp|gif)$/i', (string) ($doc->original_name ?? $doc->file_path ?? ''));
                        @endphp
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-3 py-2.5 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $doc->label() }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $doc->original_name }}</p>
                            </div>
                            @if ($isImage)
                                <button type="button" @click="lightbox = @js($url)"
                                        class="shrink-0 text-xs font-semibold text-brand hover:underline">Preview</button>
                            @else
                                <a href="{{ $url }}" target="_blank" class="shrink-0 text-xs font-semibold text-brand hover:underline">Open</a>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No documents uploaded.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl shadow-sm overflow-hidden ring-2 ring-brand/25 bg-gradient-to-b from-brand-muted/50 to-white">
                <div class="bg-brand px-6 py-4 text-white">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-brand-gold">Decision</h2>
                    <p class="text-sm text-white/80 mt-1">Approve creates the partner account and partner code.</p>
                </div>
                <form method="POST" action="{{ route('admin.partner-applications.update', $application) }}" class="p-6 space-y-4"
                      @submit.prevent="window.confirmForm($el, {
                          title: document.getElementById('pa-status')?.value === 'approved' ? 'Approve this partner?' : (document.getElementById('pa-status')?.value === 'rejected' ? 'Reject this application?' : 'Save decision?'),
                          message: document.getElementById('pa-status')?.value === 'approved'
                              ? 'This will create their partner account and partner code for activation (no SMS).'
                              : 'Confirm you want to save this decision.',
                          confirmLabel: 'Yes, save',
                          confirmClass: 'bg-brand hover:bg-brand-light text-white',
                          tone: 'confirm',
                      })">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">Status</label>
                        <select id="pa-status" name="status" class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand">
                            @foreach (['pending', 'approved', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected($application->status === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">Admin notes</label>
                        <textarea name="admin_notes" rows="4"
                                  class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm focus:border-brand focus:ring-brand"
                                  placeholder="Optional notes for the file…">{{ old('admin_notes', $application->admin_notes) }}</textarea>
                    </div>
                    @if ($application->partner_id)
                        <div class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3 text-sm">
                            <p class="text-xs uppercase tracking-widest text-brand font-semibold">Linked partner</p>
                            <a href="{{ route('admin.partners.show', $application->partner_id) }}" class="mt-1 inline-block font-bold text-brand hover:underline">
                                {{ $application->partner?->vendor_number ?? '#'.$application->partner_id }}
                            </a>
                            <p class="text-xs text-gray-500 mt-1 capitalize">Status: {{ $application->partner?->status ?? '—' }}
                                @if ($application->partner?->activated_at)
                                    · Activated
                                @else
                                    · Awaiting activation
                                @endif
                            </p>
                        </div>
                    @else
                        <p class="text-xs text-brand/80">Approving automatically creates the partner record and partner code for portal activation.</p>
                    @endif
                    <button class="w-full bg-brand hover:bg-brand-light text-white font-semibold rounded-xl px-4 py-3 text-sm shadow-sm">Save decision</button>
                </form>
            </div>
        </div>

        <div x-show="lightbox" x-cloak x-transition
             class="fixed inset-0 z-[10060] flex items-center justify-center p-4 bg-brand/80 backdrop-blur-sm"
             @keydown.escape.window="lightbox = null"
             @click.self="lightbox = null">
            <button type="button" @click="lightbox = null"
                    class="absolute top-4 right-4 rounded-full bg-white/90 text-brand font-bold px-3 py-1.5 text-sm shadow">Esc</button>
            <img :src="lightbox" alt="" class="max-h-[90vh] max-w-[95vw] object-contain rounded-xl shadow-2xl ring-1 ring-white/20">
        </div>
    </div>
</x-admin.layout>
