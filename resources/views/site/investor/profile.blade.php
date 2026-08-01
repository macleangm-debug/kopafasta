<x-site.investor-layout title="Profile — Investor" active="profile">
    <x-site.borrower-page-header
        eyebrow="Capital partner"
        title="Profile"
        subtitle="Manage your investor profile and risk preferences."
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 glass-card rounded-2xl ring-1 ring-brand/10 p-5 sm:p-6">
            <form method="POST" action="{{ route('site.investor.profile.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs uppercase tracking-widest text-brand font-semibold">Name</label>
                        <input type="text" name="name" value="{{ old('name', $lender->name) }}" required class="w-full mt-1 rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand" />
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-widest text-brand font-semibold">Email</label>
                        <input type="email" name="email" value="{{ old('email', $lender->email) }}" required class="w-full mt-1 rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand" />
                    </div>
                    <div>
                        <x-site.phone-input name="phone" label="Phone" :value="old('phone', $lender->phone)" variant="rounded" />
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-widest text-brand font-semibold">Address</label>
                        <input type="text" name="address" value="{{ old('address', $lender->address) }}" class="w-full mt-1 rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand" />
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <label class="text-xs uppercase tracking-widest text-brand font-semibold">Risk preference</label>
                    <div class="grid grid-cols-3 gap-2 mt-2">
                        @foreach (['low' => 'Low risk', 'medium' => 'Balanced', 'high' => 'High return'] as $val => $label)
                            <label @class([
                                'cursor-pointer rounded-xl border-2 p-3 text-center transition',
                                'border-brand bg-brand-muted/40' => $lender->risk_preference === $val,
                                'border-gray-200 hover:bg-brand-muted/20' => $lender->risk_preference !== $val,
                            ])>
                                <input type="radio" name="risk_preference" value="{{ $val }}" @checked($lender->risk_preference === $val) class="sr-only" />
                                <p class="font-semibold text-sm text-gray-900">{{ $label }}</p>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="auto_invest" value="1" @checked($lender->auto_invest) class="size-4 rounded border-gray-300 text-brand focus:ring-brand" />
                    <span class="text-sm text-gray-700"><strong>Auto-invest</strong> — automatically deploy idle funds into approved pools that match my risk preference.</span>
                </label>

                <button class="rounded-xl bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 text-sm">Save changes</button>
            </form>
        </div>

        <aside class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 h-fit">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Investor code</p>
            <p class="font-mono text-lg font-bold text-gray-900 mt-1">{{ $lender->code }}</p>

            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mt-4">Account type</p>
            <p class="font-semibold capitalize text-gray-800 mt-1">{{ $lender->type }}</p>

            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mt-4">Status</p>
            <span class="inline-block mt-1 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase bg-emerald-100 text-brand">{{ $lender->status }}</span>
        </aside>
    </div>
</x-site.investor-layout>
