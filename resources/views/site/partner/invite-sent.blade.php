<x-site.layout :title="brand_title('Check your messages')">
    <div class="max-w-md mx-auto py-10 px-4 text-center">
        <h1 class="text-2xl font-bold mb-2">Activation link sent</h1>
        <p class="text-sm text-gray-600 mb-4">
            We sent an activation link to {{ $vendor->email ?: $vendor->phone }} for <strong>{{ $vendor->name }}</strong>.
        </p>
        <p class="text-sm text-gray-600">Open the link to create your password and 4-digit PIN, then sign in to the partner portal.</p>
        <a href="{{ route('site.login') }}" class="inline-flex mt-8 bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-3 rounded-full text-sm">
            Go to login
        </a>
    </div>
</x-site.layout>
