<x-site.investor-layout title="Profile — Investor" active="profile">
    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mb-1">Profile</h1>
    <p class="text-slate-500 text-sm mb-6">Manage your investor profile and risk preferences.</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6">
            <form method="POST" action="{{ route('site.investor.profile.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs uppercase text-slate-500 font-semibold">Name</label>
                        <input type="text" name="name" value="{{ old('name', $lender->name) }}" required class="w-full mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs uppercase text-slate-500 font-semibold">Email</label>
                        <input type="email" name="email" value="{{ old('email', $lender->email) }}" required class="w-full mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <x-site.phone-input name="phone" label="Phone" :value="old('phone', $lender->phone)"
                            select-class="w-28 shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            input-class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs uppercase text-slate-500 font-semibold">Address</label>
                        <input type="text" name="address" value="{{ old('address', $lender->address) }}" class="w-full mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <hr class="border-slate-200" />

                <div>
                    <label class="text-xs uppercase text-slate-500 font-semibold">Risk preference</label>
                    <div class="grid grid-cols-3 gap-2 mt-1">
                        @foreach (['low' => ['Low risk', 'emerald'], 'medium' => ['Balanced', 'amber'], 'high' => ['High return', 'red']] as $val => $meta)
                            <label class="cursor-pointer rounded-lg border-2 p-3 text-center transition
                                {{ $lender->risk_preference === $val ? 'border-'.$meta[1].'-500 bg-'.$meta[1].'-50' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="radio" name="risk_preference" value="{{ $val }}" {{ $lender->risk_preference === $val ? 'checked' : '' }} class="sr-only" />
                                <p class="font-semibold text-sm">{{ $meta[0] }}</p>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="auto_invest" value="1" {{ $lender->auto_invest ? 'checked' : '' }} class="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    <span class="text-sm"><strong>Auto-invest</strong> — automatically deploy idle funds into approved pools that match my risk preference.</span>
                </label>

                <button class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 text-sm">Save changes</button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 h-fit">
            <p class="text-xs uppercase text-slate-500 font-semibold">Investor code</p>
            <p class="font-mono text-lg font-bold mt-1">{{ $lender->code }}</p>

            <p class="text-xs uppercase text-slate-500 font-semibold mt-4">Account type</p>
            <p class="font-semibold capitalize mt-1">{{ $lender->type }}</p>

            <p class="text-xs uppercase text-slate-500 font-semibold mt-4">Status</p>
            <span class="inline-block mt-1 rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase bg-emerald-100 text-emerald-700">{{ $lender->status }}</span>
        </div>
    </div>
</x-site.investor-layout>
