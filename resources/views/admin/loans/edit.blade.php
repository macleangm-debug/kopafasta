<x-admin.layout
    title="Edit loan {{ $loan->loan_number }}"
    heading="Edit loan"
    subheading="{{ $loan->loan_number }}">

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <form method="POST" action="{{ route('admin.loans.update', $loan) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.loans._form')

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.loans.show', $loan) }}"
                   class="text-sm font-medium text-gray-600 hover:text-gray-800 px-4 py-2">Cancel</a>
                <button type="submit"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2 rounded-lg shadow-sm transition">
                    Save changes
                </button>
            </div>
        </form>
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-red-200 p-6">
        <h3 class="text-sm font-semibold text-red-700 mb-1">Danger zone</h3>
        <p class="text-xs text-gray-500 mb-3">Deleting this loan will remove its repayment schedules and history.</p>
        <form method="POST" action="{{ route('admin.loans.destroy', $loan) }}"
              @submit.prevent="window.confirmForm($el, {
                  title: @js('Delete this loan?'),
                  message: @js('Delete this loan? This cannot be undone.'),
                  confirmLabel: @js('Delete loan'),
                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                  tone: 'warning',
              })">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg shadow-sm transition">
                Delete loan
            </button>
        </form>
    </div>
</x-admin.layout>
