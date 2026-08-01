<x-admin.dossier-section id="customer-personal" title="Personal information" subtitle="Identity and contact — click Edit to change">
    <x-slot:view>
        <dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">First name</dt><dd class="font-medium mt-0.5">{{ $customer->first_name ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Last name</dt><dd class="font-medium mt-0.5">{{ $customer->last_name ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">National ID (NIDA)</dt><dd class="font-medium mt-0.5">{{ $customer->national_id ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Date of birth</dt><dd class="font-medium mt-0.5">{{ optional($customer->date_of_birth)->format('d M Y') ?? '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Gender</dt><dd class="font-medium mt-0.5">{{ ucfirst($customer->gender ?? '—') }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Phone</dt><dd class="font-medium mt-0.5">{{ $customer->phone ?: '—' }}</dd></div>
            <div class="md:col-span-2"><dt class="text-xs uppercase tracking-wider text-gray-500">Email</dt><dd class="font-medium mt-0.5">{{ $customer->email ?: '—' }}</dd></div>
        </dl>
        @if ($customer->identity_locked)
            <p class="mt-4 text-xs text-amber-700 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
                Identity is locked after NIDA verification — name and NIDA cannot be changed.
            </p>
        @endif
    </x-slot:view>
    <x-slot:edit>
        <form method="POST" action="{{ route('admin.customers.section.update', [$customer, 'personal']) }}" class="grid md:grid-cols-2 gap-4">
            @csrf
            @method('PUT')
            <x-admin.input name="first_name" label="First name" :value="$customer->first_name" required />
            <x-admin.input name="last_name" label="Last name" :value="$customer->last_name" required />
            <x-admin.input name="national_id" label="National ID (NIDA)" :value="$customer->national_id" />
            <x-admin.input name="date_of_birth" label="Date of birth" type="date" :value="optional($customer->date_of_birth)->format('Y-m-d')" />
            <x-admin.select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :value="$customer->gender" placeholder="— Select —" />
            <x-admin.phone-input name="phone" label="Phone" :value="$customer->phone" required />
            <x-admin.input name="email" label="Email" type="email" :value="$customer->email" />
            @if ($customer->identity_locked)
                <p class="md:col-span-2 text-xs text-amber-700 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
                    Identity is locked after NIDA verification — name and NIDA cannot be changed here.
                </p>
            @endif
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2 rounded-lg">Save personal details</button>
            </div>
        </form>
    </x-slot:edit>
</x-admin.dossier-section>
