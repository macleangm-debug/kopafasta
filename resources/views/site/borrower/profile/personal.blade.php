<x-site.borrower-layout title="Profile — Kopafasta" active="profile">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">Profile</h1>
        <p class="text-sm text-gray-500 mb-6">Keep your personal, activity, residence and KYC details up to date.</p>

        @include('site.borrower.profile._tabs', ['active' => 'personal'])

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'personal']) }}" class="bg-white rounded-2xl border border-gray-200 p-6">
            @csrf @method('PUT')

            <h2 class="font-semibold mb-4">Personal information</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">First name</label>
                    <input name="first_name" value="{{ old('first_name', $customer->first_name) }}" required
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Last name</label>
                    <input name="last_name" value="{{ old('last_name', $customer->last_name) }}" required
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Phone</label>
                    <input name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Date of birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d')) }}" required
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                    <p class="text-[11px] text-gray-500 mt-1">Must be 18 years or older (BOT compliance).</p>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Gender</label>
                    <select name="gender" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('gender', $customer->gender) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">NIDA number</label>
                    <input name="national_id" value="{{ old('national_id', $customer->national_id) }}"
                           placeholder="XXXXXXXX-XXXXX-XXXXX-XX"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
                </div>
                <div class="sm:col-span-2 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-gray-900">Face verification</p>
                            <p class="text-xs text-gray-500 mt-0.5">Required before loan applications (Phase 2C).</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-800">Pending</span>
                    </div>
                </div>
            </div>

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Save personal information</button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
