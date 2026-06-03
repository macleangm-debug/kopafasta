<x-admin.review-section id="review-contract" title="Loan contract & offer letter" subtitle="Generated agreement, signature status and PDF">
    @if ($offer)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4">
            <div><div class="text-xs uppercase text-gray-500">Reference</div><div class="font-mono font-semibold">{{ $offer->reference }}</div></div>
            <div><div class="text-xs uppercase text-gray-500">Status</div>
                <span @class([
                    'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase mt-1',
                    'bg-emerald-100 text-emerald-800' => $offer->status === 'signed',
                    'bg-amber-100 text-amber-800'     => $offer->status === 'sent',
                    'bg-gray-100 text-gray-700'       => in_array($offer->status, ['draft','expired','cancelled']),
                ])>{{ $offer->status }}</span>
            </div>
            <div><div class="text-xs uppercase text-gray-500">Signed at</div><div>{{ optional($offer->signed_at)->format('d M Y, H:i') ?? '—' }}</div></div>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-5">
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                <p class="text-[10px] uppercase text-gray-500">Loan amount</p>
                <p class="font-semibold mt-1">{{ format_money((float) ($record->recommended_amount ?? $record->requested_amount)) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                <p class="text-[10px] uppercase text-gray-500">Tenure</p>
                <p class="font-semibold mt-1">{{ $record->requested_tenure_months }} months</p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                <p class="text-[10px] uppercase text-gray-500">Product rate</p>
                <p class="font-semibold mt-1">{{ format_number((float) ($review['product']?->interest_rate ?? 0) * 100, 2) }}% / month</p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                <p class="text-[10px] uppercase text-gray-500">Borrower signature</p>
                <p class="font-semibold mt-1">{{ $offer->isSigned() ? 'Signed' : 'Pending' }}</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($offer->file_path) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-4 py-2 rounded-lg">
                View PDF
            </a>
            <form method="POST" action="{{ route('admin.loan-applications.agreement.generate', $record) }}"
                  onsubmit="return confirm('Regenerate the offer letter? The borrower will need to sign the new version.');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                    Regenerate
                </button>
            </form>
        </div>
    @else
        <p class="text-sm text-gray-500 mb-4">No offer letter has been issued yet. Generate one when the application reaches approval stage.</p>
        <form method="POST" action="{{ route('admin.loan-applications.agreement.generate', $record) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-lg">
                Generate offer letter
            </button>
        </form>
    @endif
</x-admin.review-section>
