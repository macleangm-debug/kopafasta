<x-site.layout title="Guarantor invitation — Kopafasta">
    <div class="max-w-xl mx-auto px-4 py-12 text-center">
        <div class="bg-white rounded-2xl border border-gray-200 p-8">
            <h1 class="text-xl font-bold mb-2">Invitation closed</h1>
            <p class="text-sm text-gray-600">This guarantor invitation has already been {{ str_replace('_', ' ', $invitation->status) }}.</p>
        </div>
    </div>
</x-site.layout>
