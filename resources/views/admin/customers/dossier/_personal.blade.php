<x-admin.review-section id="customer-personal" title="Personal information" subtitle="Identity and contact — editable by loan officer">
    <form method="POST" action="{{ route('admin.customers.section.update', [$customer, 'personal']) }}" class="grid md:grid-cols-2 gap-4">
        @csrf
        @method('PUT')
        <x-admin.input name="first_name" label="First name" :value="$customer->first_name" required />
        <x-admin.input name="last_name" label="Last name" :value="$customer->last_name" required />
        <x-admin.input name="national_id" label="National ID (NIDA)" :value="$customer->national_id" />
        <x-admin.input name="date_of_birth" label="Date of birth" type="date" :value="optional($customer->date_of_birth)->format('Y-m-d')" />
        <x-admin.select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :value="$customer->gender" placeholder="— Select —" />
        <x-admin.input name="phone" label="Phone" :value="$customer->phone" required />
        <x-admin.input name="email" label="Email" type="email" :value="$customer->email" />
        @if ($customer->identity_locked)
            <p class="md:col-span-2 text-xs text-amber-700 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">
                Identity is locked after NIDA verification — name and NIDA cannot be changed here.
            </p>
        @endif
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="inline-flex text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg">Save personal details</button>
        </div>
    </form>
</x-admin.review-section>
