<x-site.borrower-layout title="Profile & KYC — Kopafasta" active="profile">

    <h1 class="text-2xl font-bold mb-1">Profile & KYC</h1>
    <p class="text-sm text-gray-500 mb-6">Keep your details up to date for faster approvals.</p>

    {{-- KYC status banner --}}
    @php
        $kycStatus = $kyc->status ?? 'pending';
        $banner = match ($kycStatus) {
            'verified','approved' => ['bg-emerald-50 border-emerald-200 text-emerald-700', 'KYC verified — you are good to go.'],
            'rejected'            => ['bg-red-50 border-red-200 text-red-700',           'KYC was rejected. Please re-upload your documents.'],
            default               => ['bg-amber-50 border-amber-200 text-amber-700',     'KYC pending review. Make sure your documents are uploaded.'],
        };
    @endphp
    <div class="rounded-2xl border px-5 py-4 mb-6 text-sm flex items-center justify-between {{ $banner[0] }}">
        <span>{{ $banner[1] }}</span>
        <a href="{{ route('site.borrower.documents') }}" class="text-xs font-semibold hover:underline">Manage documents →</a>
    </div>

    <form method="POST" action="{{ route('site.borrower.profile.update') }}" class="bg-white rounded-2xl border border-gray-200 p-6">
        @csrf @method('PUT')

        <h2 class="font-semibold mb-4">Personal details</h2>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-600 mb-1">First name</label>
                <input name="first_name" value="{{ old('first_name', $customer->first_name) }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Last name</label>
                <input name="last_name" value="{{ old('last_name', $customer->last_name) }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Phone</label>
                <input name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Date of birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d')) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">National ID (NIDA)</label>
                <input name="national_id" value="{{ old('national_id', $customer->national_id) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs text-gray-600 mb-1">Address</label>
                <input name="address" value="{{ old('address', $customer->address) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
        </div>

        <h2 class="font-semibold mt-8 mb-4">Employment & income</h2>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Employment type</label>
                <select name="employment_type" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                    @foreach (['salaried'=>'Salaried','self_employed'=>'Self-employed','business_owner'=>'Business owner','farmer'=>'Farmer','student'=>'Student','other'=>'Other'] as $v=>$l)
                        <option value="{{ $v }}" @selected(old('employment_type', $customer->employment_type) === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Business name</label>
                <input name="business_name" value="{{ old('business_name', $customer->business_name) }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Monthly income (TZS)</label>
                <input type="number" name="monthly_income" value="{{ old('monthly_income', $customer->monthly_income) }}" min="0" step="1000" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
            </div>
        </div>

        <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Save changes</button>
    </form>

</x-site.borrower-layout>
