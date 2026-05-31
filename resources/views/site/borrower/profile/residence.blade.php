<x-site.borrower-layout title="Profile — Residence — Kopafasta" active="profile">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">Profile</h1>
        <p class="text-sm text-gray-500 mb-6">Your current place of residence in Tanzania.</p>

        @include('site.borrower.profile._tabs', ['active' => 'residence'])

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'residence']) }}" class="bg-white rounded-2xl border border-gray-200 p-6">
            @csrf @method('PUT')

            <h2 class="font-semibold mb-4">Residence information</h2>
            <x-site.address-fields
                :region="old('region', $customer->region)"
                :district="old('district', $customer->district)"
                :ward="old('ward', $customer->ward)"
                :street="old('street', $customer->street ?? $customer->address)"
            />

            <p class="mt-4 text-xs text-gray-500">Supporting documents can be uploaded under KYC or during your loan application.</p>

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Save residence information</button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
