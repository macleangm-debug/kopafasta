@if (! empty($groupContract))
    <div id="group-contract" class="glass-card p-5 mb-6 scroll-mt-24"
         x-data="{
            progress: @js($groupContract),
            polling: null,
            async refresh() {
                try {
                    const res = await fetch(@js(route('site.borrower.group-contract.progress', $application)), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (! res.ok) return;
                    const data = await res.json();
                    if (data.ok && data.progress) this.progress = data.progress;
                } catch (e) {}
            },
            startPolling() {
                this.polling = setInterval(() => this.refresh(), 20000);
            },
            statusClass(status) {
                if (status === 'signed') return 'bg-emerald-100 text-emerald-800';
                if (status === 'declined') return 'bg-red-100 text-red-800';
                return 'bg-amber-100 text-amber-800';
            },
         }"
         x-init="startPolling()"
         @destroy.window="if (polling) clearInterval(polling)">
        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
            <div>
                <h2 class="font-semibold">{{ __('borrower.apply.group.contract_dashboard_title') }}</h2>
                <p class="text-xs text-gray-500 mt-1">{{ __('borrower.apply.group.contract_dashboard_hint') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span x-show="progress.all_signed" x-cloak class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-emerald-100 text-emerald-800">{{ __('borrower.apply.group.contract_all_signed') }}</span>
                <span class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.apply.group.contract_auto_refresh') }}</span>
            </div>
        </div>

        <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50 p-4 mb-4 text-sm space-y-1">
            <template x-for="(line, index) in (progress.summary || [])" :key="'summary-' + index">
                <p class="font-medium text-gray-800" x-text="line"></p>
            </template>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="pb-2 pr-3">{{ __('borrower.loan_contract.pdf.name') }}</th>
                        <th class="pb-2 pr-3">{{ __('borrower.loan_contract.pdf.member_allocation') }}</th>
                        <th class="pb-2">{{ __('borrower.loan_contract.pdf.signature_status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="member in (progress.members || [])" :key="member.id">
                        <tr>
                            <td class="py-2 pr-3">
                                <span class="font-medium" x-text="member.name"></span>
                                <span x-show="member.role === 'leader'" class="text-xs text-amber-700">({{ __('borrower.loan_contract.pdf.group_leader') }})</span>
                            </td>
                            <td class="py-2 pr-3" x-text="new Intl.NumberFormat('en-TZ', { style: 'currency', currency: 'TZS', maximumFractionDigits: 0 }).format(member.requested_amount || 0)"></td>
                            <td class="py-2">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium"
                                      :class="statusClass(member.signature_status || 'pending')"
                                      x-text="member.signature_label || (member.signature_status || 'pending')"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="(progress.replaceable || []).length" x-cloak class="border-t border-gray-100 pt-4 space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('borrower.apply.group.replacement_title') }}</h3>
            <p class="text-xs text-gray-500">{{ __('borrower.apply.group.replacement_hint') }}</p>

            @foreach ($groupContract['replaceable'] ?? [] as $replaceable)
                <div class="rounded-xl ring-1 ring-amber-200 bg-amber-50 p-4 space-y-3"
                     x-data="{
                        mode: 'internal',
                        member_no: '',
                        phone: '',
                        first_name: '',
                        last_name: '',
                        loading: false,
                        error: '',
                        invite: null,
                        async replaceInternal() {
                            this.loading = true; this.error = '';
                            try {
                                const res = await fetch(@js(route('site.borrower.group-member.replace-internal', [$application, $replaceable['id']])), {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ member_no: this.member_no, phone: this.phone }),
                                });
                                const data = await res.json();
                                if (! res.ok || ! data.ok) { this.error = data.message || 'Failed'; return; }
                                window.location.reload();
                            } catch (e) { this.error = 'Failed'; } finally { this.loading = false; }
                        },
                        async replaceExternal() {
                            this.loading = true; this.error = '';
                            try {
                                const res = await fetch(@js(route('site.borrower.group-member.replace-external', [$application, $replaceable['id']])), {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ first_name: this.first_name, last_name: this.last_name, phone: this.phone }),
                                });
                                const data = await res.json();
                                if (! res.ok || ! data.ok) { this.error = data.message || 'Failed'; return; }
                                this.invite = data.share;
                            } catch (e) { this.error = 'Failed'; } finally { this.loading = false; }
                        }
                     }">
                    <p class="text-sm font-semibold text-amber-950">{{ $replaceable['name'] }}</p>
                    @if (filled($replaceable['decline_reason'] ?? null))
                        <p class="text-xs text-amber-900">{{ $replaceable['decline_reason'] }}</p>
                    @endif

                    <div class="flex flex-wrap gap-3 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="radio" value="internal" x-model="mode" class="text-amber-500"> {{ __('borrower.apply.group_members.mode_internal') }}</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" value="external" x-model="mode" class="text-amber-500"> {{ __('borrower.apply.group_members.mode_external') }}</label>
                    </div>

                    <div x-show="mode === 'internal'" class="space-y-2">
                        <div class="flex rounded-lg ring-1 ring-gray-200 overflow-hidden">
                            <span class="inline-flex items-center px-3 bg-gray-100 text-sm font-mono text-gray-600 border-r border-gray-200">KPF-TZ-</span>
                            <input type="text" x-model="member_no" placeholder="ABC12345" class="flex-1 border-0 px-3 py-2 text-sm font-mono focus:ring-0">
                        </div>
                        <div class="flex gap-2">
                        <input type="tel" x-model="phone" placeholder="{{ __('borrower.apply.group_members.lookup_phone') }}" class="flex-1 rounded-lg border-gray-300 text-sm">
                        <button type="button" @click="replaceInternal()" :disabled="loading"
                                class="shrink-0 rounded-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 text-xs disabled:opacity-50">
                            {{ __('borrower.apply.group.replacement_add') }}
                        </button>
                        </div>
                    </div>

                    <div x-show="mode === 'external'" class="grid sm:grid-cols-2 gap-2">
                        <input type="text" x-model="first_name" placeholder="{{ __('borrower.profile.fields.first_name') }}" class="rounded-lg border-gray-300 text-sm">
                        <input type="text" x-model="last_name" placeholder="{{ __('borrower.profile.fields.last_name') }}" class="rounded-lg border-gray-300 text-sm">
                        <input type="tel" x-model="phone" placeholder="{{ __('borrower.profile.fields.phone') }}" class="rounded-lg border-gray-300 text-sm sm:col-span-2">
                        <button type="button" @click="replaceExternal()" :disabled="loading"
                                class="sm:col-span-2 rounded-full bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-2 text-xs disabled:opacity-50">
                            {{ __('borrower.apply.group.replacement_send_invite') }}
                        </button>
                    </div>

                    <p x-show="error" x-cloak class="text-xs text-red-700" x-text="error"></p>
                    <div x-show="invite?.short_url" x-cloak class="rounded-lg bg-white ring-1 ring-emerald-200 p-3 text-xs">
                        <p class="font-semibold text-emerald-900 mb-1">{{ __('borrower.apply.group_members.invite_ready') }}</p>
                        <a :href="invite?.whatsapp_url || invite?.short_url" target="_blank" class="text-emerald-800 underline">{{ __('borrower.apply.guarantor_fields.share_whatsapp') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
